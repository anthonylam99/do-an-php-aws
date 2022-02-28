<?php

namespace App\Http\Controllers;

use App\Models\DynamoDB\UsersModel;
use Aws\DynamoDb\Marshaler;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Kitar\Dynamodb\Connection;
use Tymon\JWTAuth\Facades\JWTAuth;

class UserController extends Controller
{


    public function register(Request $request)
    {
        $iam = new IAMController();

        $userCreate = $iam->createUser($request);
        $userCreatePass = $iam->createLoginUserPassword($request);
        $userCreateAccessKey = $iam->createAccessKeyForAUser($request);

        $dynamoDb = new DynamoDbController();
        $table = 'users';
        $item = [
            'UserId' => $userCreate['UserId'],
            'UserName' => $userCreate['UserName'],
            'Password' => Hash::make($request->get('password')),
            'FullName' => '',
            'PublicKey' => Crypt::encryptString($userCreateAccessKey['AccessKeyId']),
            'SecretKey' => Crypt::encryptString($userCreateAccessKey['SecretAccessKey']),
            'UserARN'   => $userCreate['Arn']
        ];
        $create = $dynamoDb->createItem($table, $item);
        $userFolder = strtolower($request->get('username'));

        $s3 = new S3Controller();

        $createBucket = $s3->createFolder($userFolder);
        $addPutObjectRole = $s3->addPutObjectRole($request, $userFolder, $userFolder, 's3:PutObject');
        dd($addPutObjectRole);
    }

    public function getAccessRole($username = '', $userId = '')
    {
        $dynamoDb = new DynamoDbController();

        $marshaler = new Marshaler();
        $table = 'users';

        $params = [
            'UserName' => $username
        ];

        $query = $dynamoDb->query($table, $params);

        if (empty($query->get('Count'))) {
            return response()->json([
                'status' => 500,
                'message' => 'Thông tin đăng nhập không hợp lệ'
            ], 500);
        } else {
            $data = json_decode($marshaler->unmarshalJson($query->get('Items')[0]), true);

            $res = [];
            $res[0] = $data['PublicKey'];
            $res[1] = $data['SecretKey'];
            return $res;
        }
    }
    public function me(Request $request)
    {
        $token = get_bearer_token($request);
        $user = get_payload($token);
        return response()->json($user);
    }
    public function login(Request $request)
    {
        $marshaler = new Marshaler();
        $dynamoDb = new DynamoDbController();

        $username = $request->get('username');
        $password = $request->get('password');

        $table = 'users';

        $params = [
            'UserName' => $username
        ];

        $query = $dynamoDb->query($table, $params);

        if (empty($query->get('Count'))) {
            return $this->unauthorized();
        } else {
            $data = json_decode($marshaler->unmarshalJson($query->get('Items')[0]), true);
            $check = Hash::check($password, $data['Password']);

            if ($check) {
                $arrIp = [];
                $timestamp = time();
                $clientIP = $this->getMyIp();
                $location = $this->getMyLocation($clientIP['ip']);

                array_push($arrIp, $clientIP['ip']);

                $headers = array('alg' => 'HS256', 'typ' => 'JWT');
                $payload = [
                    'UserARN' => $data['UserARN'],
                    'UserName' => $data['UserName'],
                    'UserId' => $data['UserId'],
                    'TimeStamp' => $timestamp,
                    'Expired' => (time() + 60),
                    'Status' => true,
                    'TrustedIp' => $arrIp,
                    'Location' => $location
                ];
                $token = generate_jwt($headers, $payload);

                return $this->respondWithToken($token);
            } else {
                return $this->unauthorized();
            }
        }
    }

    public function logout()
    {
        auth()->logout();

        return response()->json(['message' => 'Successfully logged out']);
    }

    /**
     * Refresh a token.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function refresh()
    {
        // return $this->respondWithToken();
    }

    /**
     * Get the token array structure.
     *
     * @param  string $token
     *
     * @return \Illuminate\Http\JsonResponse
     */
    protected function respondWithToken($token)
    {
        return response()->json([
            'token' => $token
        ]);
    }

    public function unauthorized()
    {
        return response()->json([
            'status' => 500,
            'message' => 'Thông tin đăng nhập không hợp lệ'
        ], 500);
    }

    public function getMyIp()
    {

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_URL, "https://api.ipify.org?format=json");

        $result = curl_exec($ch);

        curl_close($ch);

        return json_decode($result, true);
    }

    public function getMyLocation($ip)
    {
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_URL, "https://ipwhois.app/json/" . $ip);

        $result = curl_exec($ch);

        curl_close($ch);

        $res = json_decode($result, true);

        return ["{$res['latitude']},{$res['longitude']}"];
    }

    public function refreshToken()
    {
    }
    public function checkLogin()
    {
        $cookie = Cookie::get('token');
        dd($cookie);
    }

    public function getToken(Request $request)
    {
        // $token = $request->session()->get('token');
        // // $token = session('token');
        // // $token = csrf_token();
        // return response()->json(['token' => $token]);

        $connection = new Connection([
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
            'token' => env('AWS_SESSION_TOKEN', null),
            'endpoint' => env('DYNAMODB_ENDPOINT', null),
            'prefix' => '', // table prefix
        ]);

        $result = UsersModel::all();

        dd($result);
    }
}
