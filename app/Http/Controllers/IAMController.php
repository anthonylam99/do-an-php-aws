<?php

namespace App\Http\Controllers;

use Aws\Credentials\Credentials;
use Aws\Exception\AwsException;
use Aws\Iam\IamClient;
use Illuminate\Http\Request;

class IAMController extends Controller
{
    public function createUser(Request $request)
    {
        $username = $request->get('username', '');

        if (!empty($username)) {
            $credentials = new Credentials(config('aws.s3.key'), config('aws.s3.secret'));

            $client = new IamClient([
                'region' => config('aws.s3.region'),
                'version' => 'latest',
                'credentials' => $credentials
            ]);
            try {
                $result = $client->createUser([
                    'UserName' => $username,
                    'Path' => '/'
                ]);
                return $result->get('User');
            } catch (AwsException $e) {
                dd($e->getMessage());
            }
        }
    }
    
    public function createLoginUserPassword(Request $request)
    {

        $username = $request->get('username');
        $password = $request->get('password');

        $credentials = new Credentials(config('aws.s3.key'), config('aws.s3.secret'));
        $s3 = new IamClient([
            'version'     => 'latest',
            'region'      => 'ap-southeast-3',
            'credentials' => $credentials
        ]);


        try {
            $result = $s3->createLoginProfile([
                'UserName' => $username,
                'Password' => $password
            ]);
            return $result;
        } catch (AwsException $e) {
            dd($e->getMessage());
        }
    }

    public function createAccessKeyForAUser(Request $request)
    {
        $username = $request->get('username', '');

        $credentials = new Credentials(config('aws.s3.key'), config('aws.s3.secret'));

        $client = new IamClient([
            'region' => config('aws.s3.region'),
            'version' => 'latest',
            'credentials' => $credentials
        ]);

        try {
            $result = $client->createAccessKey([
                'UserName' => $username,
            ]);
            return $result->get('AccessKey');
        } catch (AwsException $e) {
            dd($e->getMessage());
        }
    }

    public function listUsers()
    {
        $credentials = new Credentials(config('aws.s3.key'), config('aws.s3.secret'));

        $client = new IamClient([
            'region' => config('aws.s3.region'),
            'version' => 'latest',
            'credentials' => $credentials
        ]);

        try {
            $result = $client->listUsers();
            dd($result);
        } catch (AwsException $e) {
            // output error message if fails
            dd($e->getMessage());
        }
    }
}
