<?php

namespace App\Http\Controllers;

use Aws\Credentials\Credentials;
use Aws\DynamoDb\DynamoDbClient;
use Aws\DynamoDb\Exception\DynamoDbException;
use Aws\DynamoDb\Marshaler;
use Aws\Exception\AwsException;
use Aws\Sdk;
use Illuminate\Http\Request;

class DynamoDbController extends Controller
{
    public function createTable()
    {
        $credentials = new Credentials(config('aws.s3.key'), config('aws.s3.secret'));
        $options = [
            'version'     => 'latest',
            'region'      => 'ap-southeast-3',
            'credentials' => $credentials,
            'endpoint'   => 'http://localhost:8030',
        ];
        $sdk = new Sdk($options);

        $dynamoDb = $sdk->createDynamoDb();

        $params = [
            'TableName' => 'users',
            'KeySchema' => [
                [
                    'AttributeName' => 'UserName',
                    'KeyType' => 'HASH',
                ],
                [
                    'AttributeName' => 'Password',
                    'KeyType' => 'RANGE',
                ]
            ],
            'AttributeDefinitions' => [
                [
                    'AttributeName' => 'UserName',
                    'AttributeType' => 'S',
                ],
                [
                    'AttributeName' => 'Password',
                    'AttributeType' => 'S',
                ]
            ],
            'ProvisionedThroughput' => [
                'ReadCapacityUnits' => 5,
                'WriteCapacityUnits' => 5,
            ]
        ];


        try {
            $result = $dynamoDb->createTable($params);
            dd($result);
        } catch (AwsException $e) {
            // dd($e->getMessage());
            echo $e->getMessage();
        }
    }

    public function deleteTable()
    {
        $credentials = new Credentials(config('aws.s3.key'), config('aws.s3.secret'));
        $options = [
            'version'     => 'latest',
            'region'      => 'ap-southeast-3',
            'credentials' => $credentials,
            'endpoint'   => 'http://localhost:8030',
        ];
        $sdk = new Sdk($options);

        $dynamoDb = $sdk->createDynamoDb();

        try {
            $result = $dynamoDb->deleteTable([
                'TableName' => 'users'
            ]);
            echo "Deleted table.\n";
        } catch (DynamoDbException $e) {
            echo "Unable to delete table:\n";
            echo $e->getMessage() . "\n";
        }
    }

    public function createItem($table = '', $item = [])
    {
        $credentials = new Credentials(config('aws.s3.key'), config('aws.s3.secret'));
        $options = [
            'version'     => 'latest',
            'region'      => 'ap-southeast-3',
            'credentials' => $credentials,
            'endpoint'   => 'http://localhost:8030',
        ];
        $sdk = new Sdk($options);


        $dynamoDb = $sdk->createDynamoDb();
        $marshaler = new Marshaler();

        // $table = 'users';

        $params = json_encode($item);

        $item = $marshaler->marshalJson($params);

        $query = [
            'TableName' => $table,
            'Item' => $item
        ];
        try {
            $result = $dynamoDb->putItem($query);
            return $result;
        } catch (DynamoDbException $e) {
            echo "Unable to add item:\n";
            echo $e->getMessage() . "\n";
        }
    }

    public function getItem()
    {
        $credentials = new Credentials(config('aws.user.key'), config('aws.s3.secret'));
        $options = [
            'version'     => 'latest',
            'region'      => 'ap-southeast-3',
            'credentials' => $credentials,
            'endpoint'   => 'http://localhost:8030',
        ];
        $sdk = new Sdk($options);

        $dynamoDb = $sdk->createDynamoDb();
        $marshaler = new Marshaler();

        $params = json_encode([
            'UserId' => 1,
            'UserName' => 'thanglong12152@gmail.com',
        ]);

        $key = $marshaler->marshalJson($params);

        $table = 'users';

        $query = [
            'TableName' => $table,
            'Key' => $key
        ];

        try {
            $result = $dynamoDb->getItem($query);
            dd($result);
            // dd(json_decode($marshaler->unmarshalJson($result['Item']), true));
        } catch (DynamoDbException $e) {
            echo "Unable to add item:\n";
            echo $e->getMessage() . "\n";
        }
    }

    public function updateItem(Request $request)
    {
        $credentials = new Credentials(config('aws.s3.key'), config('aws.s3.secret'));
        $options = [
            'version'     => 'latest',
            'region'      => 'ap-southeast-3',
            'credentials' => $credentials,
            'endpoint'   => 'http://localhost:8030',
        ];
        $sdk = new Sdk($options);

        $dynamoDb = $sdk->createDynamoDb();
        $marshaler = new Marshaler();

        $data = $request->all();
        $table = 'users';

        $paramsKey = $request->get('key');
        $params = json_encode($paramsKey);
        $dataUpdate = $request->get('params');



        $arrUpdate = [];
        $stringUpdate = 'set ';
        $index = 0;
        foreach ($dataUpdate as $keyUpdate => $value) {
            $arrUpdate[":{$keyUpdate}"] = $value;

            $index++;
            $stringUpdate .= "{$keyUpdate} = :{$keyUpdate}";
            if ($index > 0 && $index <= count($dataUpdate) - 1) {
                $stringUpdate .= ' ,';
            }
        }
        $paramsUpdate = json_encode($arrUpdate);



        $key = $marshaler->marshalJson($params);
        $eav = $marshaler->marshalJson($paramsUpdate);

        $query = [
            'TableName' => $table,
            'Key' => $key,
            'UpdateExpression' => $stringUpdate,
            'ExpressionAttributeValues' => $eav,
            'ReturnValues' => 'UPDATED_NEW'
        ];

        try {
            $result = $dynamoDb->updateItem($query);
            dd($result);
        } catch (DynamoDbException $e) {
            echo "Unable to update item:\n";
            echo $e->getMessage() . "\n";
        }
    }

    public function removeItem(Request $request)
    {
        $credentials = new Credentials(config('aws.s3.key'), config('aws.s3.secret'));
        $options = [
            'version'     => 'latest',
            'region'      => 'ap-southeast-3',
            'credentials' => $credentials,
            'endpoint'   => 'http://localhost:8030',
        ];
        $sdk = new Sdk($options);

        $dynamoDb = $sdk->createDynamoDb();
        $marshaler = new Marshaler();

        $table = 'users';

        $paramsKey = json_encode([
            'UserId' => 1,
            'UserName' => 'thanglong12152@gmail.com',
        ]);

        $key = $marshaler->marshalJson($paramsKey);

        $params = [
            'TableName' => $table,
            'Key' => $key
        ];

        try {
            $result = $dynamoDb->deleteItem($params);
            dd($result);
        } catch (DynamoDbException $e) {
            echo "Unable to delete item:\n";
            echo $e->getMessage() . "\n";
        }
    }
    public function query($table = '', $arrParam = [])
    {
        $credentials = new Credentials(config('aws.s3.key'), config('aws.s3.secret'));
        $options = [
            'version'     => 'latest',
            'region'      => 'ap-southeast-3',
            'credentials' => $credentials,
            'endpoint'   => 'http://localhost:8030',
        ];
        $sdk = new Sdk($options);

        $dynamoDb = $sdk->createDynamoDb();
        $marshaler = new Marshaler();


        $KeyConditionExpression = '';
        $index = 0;
        $ExpressionAttributeNames = [];
        foreach ($arrParam  as $key => $value) {
            $KeyConditionExpression .= "#{$key} = :{$key}";
            $ExpressionAttributeNames["#{$key}"] = $key;

            $index++;
            if ($index > 0 && $index <= count($arrParam) - 1) {
                $KeyConditionExpression .= ' and ';
            }
        }


        foreach ($arrParam as $key =>  $value) {
            $arrParam[":{$key}"] = $arrParam[$key];
            unset($arrParam[$key]);
        }
        $eav = $marshaler->marshalJson(json_encode($arrParam));



        $params = [
            'TableName' => $table,
            'KeyConditionExpression' => $KeyConditionExpression,
            'ExpressionAttributeNames' => $ExpressionAttributeNames,
            'ExpressionAttributeValues' => $eav
        ];
        // dd($params);    

        try {
            $result = $dynamoDb->query($params);
            return $result;
        } catch (DynamoDbException $e) {
            echo "Unable to query:\n";
            echo $e->getMessage() . "\n";
        }
    }

    public function scan($table = '', $params = [])
    {
        $credentials = new Credentials(config('aws.s3.key'), config('aws.s3.secret'));
        $options = [
            'version'     => 'latest',
            'region'      => 'ap-southeast-3',
            'credentials' => $credentials,
            'endpoint'   => 'http://localhost:8030',
        ];
        $sdk = new Sdk($options);

        $dynamoDb = $sdk->createDynamoDb();
        $marshaler = new Marshaler();

        $eav = $marshaler->marshalJson(json_encode([
            ':UserId' => 'AIDAV6R3ZBLIZA5SRTMWA'
        ]));

        $params = [
            'TableName' => $table,
            'FilterExpression' => 'UserId = :UserId',
            'ExpressionAttributeValues' => $eav
        ];

        try {
            $result = $dynamoDb->scan($params);
            dd($result);
        } catch (DynamoDbException $e) {
            dd($e->getMessage());
        }
    }
}
