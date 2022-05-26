<?php

namespace App\Http\Controllers;

use Aws\S3\Exception\S3Exception;
use Aws\S3\S3Client;
use Illuminate\Http\Request;
use Aws\Credentials\Credentials;
use Aws\Crypto\KmsMaterialsProvider;
use Aws\Crypto\KmsMaterialsProviderV2;
use Aws\Exception\AwsException;
use Aws\Iam\IamClient;
use Aws\Kms\KmsClient;
use Aws\S3\Crypto\S3EncryptionClient;
use Aws\S3\Crypto\S3EncryptionClientV2;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Crypt;
use PDO;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\SimpleType\DocProtect;

use function Aws\recursive_dir_iterator;

class S3Controller extends Controller
{
    public function listBucket(){
        $credentials = new Credentials(config('aws.s3.key'), config('aws.s3.secret'));

        $options = [
            'version'     => 'latest',
            'region'      => 'ap-southeast-3',
            'credentials' => $credentials
        ];
        $s3 = new S3Client($options);

        $buckets = $s3->listBuckets();
        dd($buckets);
        foreach ($buckets['Buckets'] as $bucket) {
            echo $bucket['Name'] . "\n";
        }
    }

    public function createBucket(Request $request)
    {
        $bucket = $request->get('bucket', '');
        $credentials = new Credentials(config('aws.s3.key'), config('aws.s3.secret'));

        $options = [
            'version'     => 'latest',
            'region'      => 'ap-southeast-3',
            'credentials' => $credentials
        ];
        $s3 = new S3Client($options);

        try {
            $result = $s3->createBucket([
                'ACL' => 'private',
                'Bucket' => $bucket,
                'CreateBucketConfiguration' => [
                    'LocationConstraint' => 'ap-southeast-3',
                ],
                'ObjectOwnership' => 'BucketOwnerPreferred'
            ]);
            return $result;
        } catch (S3Exception $e) {
            return response()->json($e->getMessage());
        }
    }

    public function createFolder($folder = '')
    {
        $credentials = new Credentials(config('aws.s3.key'), config('aws.s3.secret'));

        $options = [
            'version'     => 'latest',
            'region'      => 'ap-southeast-3',
            'credentials' => $credentials
        ];
        $s3 = new S3Client($options);

        try {
            $result =  $s3->putObject(array(
                'Bucket' => config('aws.s3.bucket'),
                'Key'    => "{$folder}/",
                'Body'   => "",
                'ACL'    => 'private'
            ));
            return $result;
        } catch (S3Exception $e) {
            return response()->json($e->getMessage());
        }
    }

    public function uploadFile(Request $request)
    {
        $user = new UserController();

        $cookie = Cookie::get('token');
        $username = $request->get('username');
//        $access = $user->getAccessRole($username);

//        $credentials = new Credentials(Crypt::decryptString($access[0]), Crypt::decryptString($access[1]));
        $credentials = new Credentials(config('aws.s3.key'), config('aws.s3.secret'));
        $options = [
            'version'     => 'latest',
            'region'      => 'ap-southeast-3',
            'credentials' => $credentials
        ];
        $s3 = new S3Client($options);
        $file = $request->file('file');

        $fileName = $file->getClientOriginalName();
        $tmpName = $file->getPathname();
        $filepath = public_path('uploads/');

        $extension = explode('.', $fileName);
        $extension = strtolower(end($extension));

        $key = md5(uniqid());
        $tmp_file_name = "{$key}.{$extension}";
        $tmp_file_path = $filepath . $tmp_file_name;


        $file->move($filepath, $tmp_file_name);

        $userFolder = strtolower($username);
        try {
            $s3->putObject([
                'Bucket' => config('aws.s3.bucket'),
                'Key'    => "{$userFolder}/{$fileName}",
                'Body'   => fopen(public_path() . '/uploads/' . $tmp_file_name, 'rb'),
            ]);

            unlink(public_path() . '/uploads/' . $tmp_file_name);
        } catch (S3Exception $e) {
            return response()->json($e->getMessage());
        }
    }

    public function uploadFileEncrypt(Request $request)
    {
        $credentials = new Credentials(config('aws.s3.key'), config('aws.s3.secret'));

        // Let's construct our S3EncryptionClient using an S3Client
        $encryptionClient = new S3EncryptionClientV2(
            new S3Client([
                'region' => 'ap-southeast-3',
                'version' => 'latest',
                'credentials' => $credentials
            ])
        );

        $kmsKeyArn = 'arn:aws:kms:ap-southeast-3:409221008081:key/cd8bb123-38d6-41ac-a532-0a838d4413f1';
        // This materials provider handles generating a cipher key and initialization
        // vector, as well as encrypting your cipher key via AWS KMS
        $materialsProvider = new KmsMaterialsProviderV2(
            new KmsClient([
                'region' => 'ap-southeast-3',
                'version' => 'latest',
                'credentials' => $credentials
            ]),
            $kmsKeyArn
        );

        $bucket = config('aws.s3.bucket');
        $key = config('aws.s3.key');
        $cipherOptions = [
            'Cipher' => 'gcm',
            'KeySize' => 256,
            // Additional configuration options
        ];



        $file = $request->file('file');
        $fileName = $file->getClientOriginalName();
        $tmpName = $file->getPathname();
        $filepath = public_path('uploads/');

        $extension = explode('.', $fileName);
        $extension = strtolower(end($extension));

        $key = md5(uniqid());
        $tmp_file_name = "{$key}.{$extension}";
        $tmp_file_path = $filepath . $tmp_file_name;

        $file->move($filepath, $tmp_file_name);

        $contentType = mime_content_type(public_path() . '/uploads/' . $tmp_file_name);

        $result = $encryptionClient->putObject([
            '@MaterialsProvider' => $materialsProvider,
            '@CipherOptions' => $cipherOptions,
            '@KmsEncryptionContext' => ['context-key' => 'context-value'],
            'Bucket' => $bucket,
            'Key' => "uploads/{$tmp_file_name}",
            'Body' => fopen(public_path() . '/uploads/' . $tmp_file_name, 'rb'),
            'Content-Type' => $contentType
        ]);

        unlink(public_path() . '/uploads/' . $tmp_file_name);
        dd($result);
    }

    public function readingFile()
    {
         $credentials = new Credentials(config('aws.s3.key'), config('aws.s3.secret'));
//        $credentials = new Credentials(config('aws.user3.key'), config('aws.user3.secret'));

        $options = [
            'version'     => 'latest',
            'region'      => 'ap-southeast-3',
            'credentials' => $credentials
        ];
        $s3 = new S3Client($options);
        $bucket = config('aws.s3.bucket');
        try {
            // $result = $s3->getObject([
            //     'Bucket' => $bucket,
            //     'Key' => 'uploads/1.txt',
            //     'SaveAs' => public_path('downloads/1.txt')
            // ]);
            //Creating a presigned URL
            $cmd = $s3->getCommand('GetObject', [
                'Bucket' => $bucket,
                'Key' => 'uploads/Long.docx',
            ]);

            $request = $s3->createPresignedRequest($cmd, '+20 minutes');

            // Get the actual presigned-url
            $presignedUrl = (string)$request->getUri();
            dd($presignedUrl);
        } catch (AwsException $e) {
            dd($e->getMessage());
        }
    }

    public function decryptFile(Request $request)
    {
        $credentials = new Credentials(config('aws.s3.key'), config('aws.s3.secret'));
        // $credentials = new Credentials(config('aws.user2.key'), config('aws.user2.secret'));

        // Let's construct our S3EncryptionClient using an S3Client
        $encryptionClient = new S3EncryptionClientV2(
            new S3Client([
                'region' => 'ap-southeast-3',
                'version' => 'latest',
                'credentials' => $credentials
            ])
        );

        $kmsKeyArn = 'arn:aws:kms:ap-southeast-3:409221008081:key/cd8bb123-38d6-41ac-a532-0a838d4413f1';
        // This materials provider handles generating a cipher key and initialization
        // vector, as well as encrypting your cipher key via AWS KMS
        $materialsProvider = new KmsMaterialsProviderV2(
            new KmsClient([
                'region' => 'ap-southeast-3',
                'version' => 'latest',
                'credentials' => $credentials
            ]),
            $kmsKeyArn
        );
        $bucket = config('aws.s3.bucket');
        $key = config('aws.s3.key');
        $cipherOptions = [
            'Cipher' => 'gcm',
            'KeySize' => 256,
            // Additional configuration options
        ];

        try {
            $result = $encryptionClient->getObject([
                '@KmsAllowDecryptWithAnyCmk' => true,
                '@MaterialsProvider' => $materialsProvider,
                '@CipherOptions' => $cipherOptions,
                '@SecurityProfile' => 'V2',
                'Bucket' => $bucket,
                'Key' => 'uploads/551902fa5f8d0990c8527e511c1b045a.docx',
                'SaveAs' => public_path('downloads/551902fa5f8d0990c8527e511c1b045a.docx')
            ]);
        } catch (AwsException $e) {
            dd($e->getMessage());
        }
        dd($result);
    }

    public function getBucketACL()
    {
        $credentials = new Credentials(config('aws.s3.key'), config('aws.s3.secret'));
        $options = [
            'version'     => 'latest',
            'region'      => 'ap-southeast-3',
            'credentials' => $credentials
        ];
        $s3 = new S3Client($options);
        $bucket = 'upload.file.test';
        try {
            $resp = $s3->getObjectAcl([
                'Bucket' => $bucket,
                'Key' => 'uploads/1.txt'
            ]);
            echo "Succeed in retrieving bucket ACL as follows: \n";
            dd($resp->get('Grants'));
        } catch (AwsException $e) {
            // output error message if fails
            echo $e->getMessage();
            echo "\n";
        }
    }

    public function getUser(Request $request)
    {

        $credentials = new Credentials(config('aws.s3.key'), config('aws.s3.secret'));
        // $credentials = new Credentials(config('aws.user3.key'), config('aws.user3.secret'));
        $client = new IamClient([
            'region' => config('aws.s3.region'),
            'version' => 'latest',
            'credentials' => $credentials
        ]);

        $username = $request->get('username', '');

        try {
            $result = $client->getUser([
                'UserName' => $username
            ]);
            return $result->get('User');
        } catch (AwsException $e) {
            // output error message if fails
            dd($e->getMessage());
        }
    }

    public function listPolicies()
    {
        $credentials = new Credentials(config('aws.s3.key'), config('aws.s3.secret'));

        $client = new IamClient([
            'region' => config('aws.s3.region'),
            'version' => 'latest',
            'credentials' => $credentials
        ]);

        try {
            $result = $client->listPolicies([]);
            dd($result);
        } catch (AwsException $e) {
            dd($e->getMessage());
        }
    }

    public function attachUserPolicy()
    {
        $credentials = new Credentials(config('aws.s3.key'), config('aws.s3.secret'));

        $client = new IamClient([
            'region' => config('aws.s3.region'),
            'version' => 'latest',
            'credentials' => $credentials
        ]);

        try {
            $result = $client->attachUserPolicy([
                'PolicyArn' => 'arn:aws:iam::409221008081:policy/PutObjectToBucket',
                'UserName' => 'Longtest3'
            ]);
            dd($result);
        } catch (AwsException $e) {
            dd($e->getMessage());
        }
    }

    public function addPutObjectRole(Request $request, $file = '', $users = '', $role = '')
    {
        $credentials = new Credentials(config('aws.s3.key'), config('aws.s3.secret'));
        $options = [
            'version'     => 'latest',
            'region'      => 'ap-southeast-3',
            'credentials' => $credentials
        ];
        $s3 = new S3Client($options);

        $bucket = config('aws.s3.bucket');
        $file = "{$users}/*";

        $bucketPolicy = [];

        /*******GET BUCKET POLICY*********** */
        try {
            $resp = $s3->getBucketPolicy([
                'Bucket' => $bucket
            ]);
            $bucketPolicy = json_decode($resp->get('Policy'));
        } catch (AwsException $e) {
            dd($e->getMessage());
        }

        if (!empty($bucketPolicy)) {
            $dataUser = $this->getUser($request);
            $userArn = $dataUser['Arn'];
            $params = [];
            $i = 0;

            foreach ($bucketPolicy->Statement as $key => $value) {
                if (($value->Action == $role) && ($value->Resource == "arn:aws:s3:::{$bucket}/{$file}")) {
                    if (!is_array($value->Principal->AWS)) {
                        $arrUser = array($value->Principal->AWS);
                        array_push($arrUser, $userArn);
                        array_unique($arrUser);

                        $value->Principal->AWS = array_unique($arrUser);
                    } else {
                        array_push($value->Principal->AWS, $userArn);
                        $value->Principal->AWS = array_unique($value->Principal->AWS);
                    }
                    break;
                } else {
                    $i++;
                }
            }
            if ($i === count($bucketPolicy->Statement)) {
                $params = (object) [
                    "Sid" => "Allow getObject {$file}",
                    "Effect" => "Allow",
                    "Principal" => (object) [
                        "AWS" => [
                            $userArn
                        ]
                    ],
                    "Action" => $role,
                    "Resource" =>  "arn:aws:s3:::{$bucket}/{$file}"
                ];
            }
            if (!empty($params)) {
                array_push($bucketPolicy->Statement, $params);
            }

            try {
                $result = $s3->putBucketPolicy([
                    'Bucket' => $bucket,
                    'Policy' => json_encode($bucketPolicy)
                ]);
                return $result;
            } catch (AwsException $e) {
                dd($e->getMessage());
            }
            dd($bucketPolicy);
        }
    }

    public function addUserToAFile(Request $request)
    {
        $credentials = new Credentials(config('aws.s3.key'), config('aws.s3.secret'));
        $options = [
            'version'     => 'latest',
            'region'      => 'ap-southeast-3',
            'credentials' => $credentials
        ];
        $s3 = new S3Client($options);

        $bucket = $request->get('bucket', '');
        $file = $request->get('file');

        $bucketPolicy = [];

        /*******GET BUCKET POLICY*********** */
        try {
            $resp = $s3->getBucketPolicy([
                'Bucket' => $bucket
            ]);
            $bucketPolicy = json_decode($resp->get('Policy'));
        } catch (AwsException $e) {
            dd($e->getMessage());
        }

        if (!empty($bucketPolicy)) {
            $dataUser = $this->getUser($request);
            $userArn = $dataUser['Arn'];
            $params = [];
            $i = 0;

            foreach ($bucketPolicy->Statement as $key => $value) {
                if (($value->Action == "s3:GetObject") && ($value->Resource == "arn:aws:s3:::{$bucket}/{$file}")) {
                    if (!is_array($value->Principal->AWS)) {
                        $arrUser = array($value->Principal->AWS);
                        array_push($arrUser, $userArn);
                        array_unique($arrUser);

                        $value->Principal->AWS = array_unique($arrUser);
                    } else {
                        array_push($value->Principal->AWS, $userArn);
                        $value->Principal->AWS = array_unique($value->Principal->AWS);
                    }
                    break;
                } else {
                    $i++;
                }
            }
            if ($i === count($bucketPolicy->Statement)) {
                $params = (object) [
                    "Sid" => "Allow getObject {$file}",
                    "Effect" => "Allow",
                    "Principal" => (object) [
                        "AWS" => [
                            $userArn
                        ]
                    ],
                    "Action" => "s3:GetObject",
                    "Resource" =>  "arn:aws:s3:::{$bucket}/{$file}"
                ];
            }
            if (!empty($params)) {
                array_push($bucketPolicy->Statement, $params);
            }

            try {
                $result = $s3->putBucketPolicy([
                    'Bucket' => $bucket,
                    'Policy' => json_encode($bucketPolicy)
                ]);
                dd($result);
            } catch (AwsException $e) {
                dd($e->getMessage());
            }
            dd($bucketPolicy);
        }
    }


    public function getBucketPolicy()
    {
        $credentials = new Credentials(config('aws.s3.key'), config('aws.s3.secret'));

        $options = [
            'version'     => 'latest',
            'region'      => 'ap-southeast-3',
            'credentials' => $credentials
        ];
        $s3 = new S3Client($options);

        try {
            $resp = $s3->getBucketPolicy([
                'Bucket' => 'upload.file.test'
            ]);
            echo $resp->get('Policy');
        } catch (AwsException $e) {
            dd($e->getMessage());
        }
    }

    public function getPolicy()
    {
        $credentials = new Credentials(config('aws.s3.key'), config('aws.s3.secret'));

        $options = [
            'version'     => 'latest',
            'region'      => 'ap-southeast-3',
            'credentials' => $credentials
        ];
        $s3 = new IamClient($options);

        try {
            $result = $s3->getPolicy([]);
        } catch (AwsException $e) {
            dd($e->getMessage());
        }
    }

    public function listPolicyKMS()
    {
        $credentials = new Credentials(config('aws.s3.key'), config('aws.s3.secret'));
        $KmsClient = new KmsClient([
            'version'     => 'latest',
            'region'      => 'ap-southeast-3',
            'credentials' => $credentials
        ]);

        $keyId = 'cd8bb123-38d6-41ac-a532-0a838d4413f1';
        $limit = 10;

        try {
            $result = $KmsClient->getKeyPolicy([
                'KeyId' => $keyId,
                'PolicyName' => 'default'
            ]);

            $policy = json_decode($result->get('Policy'));
            return $policy;
        } catch (AwsException $e) {
            // output error message if fails
            echo $e->getMessage();
            echo "\n";
        }
    }

    public function listObjects(Request $request)
    {
        $credentials = new Credentials(config('aws.s3.key'), config('aws.s3.secret'));
        $s3 = new S3Client([
            'version'     => 'latest',
            'region'      => 'ap-southeast-3',
            'credentials' => $credentials
        ]);

        try {
            $result = $s3->listObjects([
                'Bucket' => $request->get('bucket')
            ]);
            dd($result);
        } catch (AwsException $e) {
            // output error message if fails
            echo $e->getMessage();
            echo "\n";
        }
    }

    public function grantDecryptionFileForUser(Request $request)
    {
        $keyId = 'cd8bb123-38d6-41ac-a532-0a838d4413f1';

        $credentials = new Credentials(config('aws.s3.key'), config('aws.s3.secret'));
        $KmsClient = new KmsClient([
            'version'     => 'latest',
            'region'      => 'ap-southeast-3',
            'credentials' => $credentials
        ]);

        $objectPolicy = $this->listPolicyKMS();

        $dataUser = $this->getUser($request);  //request->username
        $userArn = $dataUser['Arn'];

        $objectAllowTheKey = $objectPolicy->Statement[2]->Principal->AWS;
        $objectAttachmentResource = $objectPolicy->Statement[3]->Principal->AWS;

        array_push($objectAllowTheKey, $userArn);
        array_push($objectAttachmentResource, $userArn);

        $objectPolicy->Statement[2]->Principal->AWS = array_unique($objectAllowTheKey);
        $objectPolicy->Statement[3]->Principal->AWS = array_unique($objectAttachmentResource);



        try {
            $result = $KmsClient->putKeyPolicy([
                'KeyId'     => $keyId,
                'Policy'    => json_encode($objectPolicy),
                'PolicyName' => 'default'
            ]);
            dd($result);
        } catch (AwsException $e) {
            dd($e->getMessage());
        }

        dd($objectPolicy);
    }

    public function removeGrantDecryptionOfAUser(Request $request)
    {
        $keyId = 'cd8bb123-38d6-41ac-a532-0a838d4413f1';

        $credentials = new Credentials(config('aws.s3.key'), config('aws.s3.secret'));
        $KmsClient = new KmsClient([
            'version'     => 'latest',
            'region'      => 'ap-southeast-3',
            'credentials' => $credentials
        ]);

        $objectPolicy = $this->listPolicyKMS();

        $dataUser = $this->getUser($request);  //request->username
        $userArn = $dataUser['Arn'];

        $objectAllowTheKey = $objectPolicy->Statement[2]->Principal->AWS;
        $objectAttachmentResource = $objectPolicy->Statement[3]->Principal->AWS;

        $newArr1 = $newArr2 = [];

        foreach ($objectAllowTheKey as $value) {
            if ($value !== $userArn) {
                array_push($newArr1, $value);
            }
        }

        foreach ($objectAttachmentResource as $value) {
            if ($value !== $userArn) {
                array_push($newArr2, $value);
            }
        }

        $objectPolicy->Statement[2]->Principal->AWS = array_unique($newArr1);
        $objectPolicy->Statement[3]->Principal->AWS = array_unique($newArr2);


        try {
            $result = $KmsClient->putKeyPolicy([
                'KeyId'     => $keyId,
                'Policy'    => json_encode($objectPolicy),
                'PolicyName' => 'default'
            ]);
            dd($result);
        } catch (AwsException $e) {
            dd($e->getMessage());
        }
        dd($objectPolicy);
    }



    public function listAttachedUserPolicy(Request $request)
    {
        $username = $request->get('username');

        $credentials = new Credentials(config('aws.s3.key'), config('aws.s3.secret'));
        $s3 = new IamClient([
            'version'     => 'latest',
            'region'      => 'ap-southeast-3',
            'credentials' => $credentials
        ]);

        try {
            $result = $s3->listAttachedUserPolicies([
                'UserName' => $username
            ]);
            dd($result);
        } catch (AwsException $e) {
            dd($e->getMessage());
        }
    }
}
