<?php

use App\Http\Controllers\DynamoDbController;
use App\Http\Controllers\IAMController;
use App\Http\Controllers\S3Controller;
use App\Http\Controllers\TestController;
use App\Http\Controllers\UserController;
use App\Http\Middleware\CheckTokenExpired;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::get('get-my-ip', [UserController::class, 'getMyIp']);

Route::get('get-token', [UserController::class, 'getToken']);

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::post('create-bucket', [S3Controller::class, 'createBucket']);

Route::get('upload', [S3Controller::class, 'uploadFile'])->middleware(CheckTokenExpired::class);

Route::post('upload-secret', [S3Controller::class, 'uploadFileEncrypt']);

Route::get('reading-file', [S3Controller::class, 'readingFile']);

Route::get('decrytion-file', [S3Controller::class, 'decryptFile']);

Route::get('get-user', [S3Controller::class, 'getUser']);

Route::get('get-bucket-acl', [S3Controller::class, 'getBucketACL']);

Route::post('create-user', [UserController::class, 'createUser']);

Route::post('create-access-key', [S3Controller::class, 'createAccessKeyForAUser']);

Route::get('get-policies', [S3Controller::class, 'listPolicies']);

Route::get('attach-user-policy', [S3Controller::class, 'attachUserPolicy']);

Route::get('add-user-to-file', [S3Controller::class, 'addUserToAFile']);

Route::get('get-bucket-policy', [S3Controller::class, 'getBucketPolicy']);

Route::post('attached-bucket-policy', [S3Controller::class, 'attachedBucketPolicy']);

Route::get('list-policy-kms', [S3Controller::class, 'listPolicyKMS']);

Route::get('list-objects', [S3Controller::class, 'listObjects']);

Route::get('grant-decryption-for-a-user', [S3Controller::class, 'grantDecryptionFileForUser']);

Route::get('remove-grant-decryption-for-a-user', [S3Controller::class, 'removeGrantDecryptionOfAUser']);

Route::post('create-login-password', [IAMController::class, 'createLoginUserPassword']);

Route::get('list-attached-user-policy', [S3Controller::class, 'listAttachedUserPolicy']);


/*****************DYNAMO DB CLIENT******************* */
Route::get('create-table', [DynamoDbController::class, 'createTable']);

Route::delete('delete-table', [DynamoDbController::class, 'deleteTable']);

Route::post('create-item', [DynamoDbController::class, 'createItem']);

Route::get('get-item', [DynamoDbController::class, 'getItem']);

Route::post('update-item', [DynamoDbController::class, 'updateItem']);

Route::post('remove-item', [DynamoDbController::class, 'removeItem']);

Route::post('query-db', [DynamoDbController::class, 'query']);

Route::get('scan-db', [DynamoDbController::class, 'scan']);

/******************USERS MANAGEMENT************************************ */
Route::post('register', [UserController::class, 'register']);

Route::group([
    'middleware' => ['api', 'token']
], function () {
    Route::get('me', [UserController::class, 'me']);
});


Route::post('login', [UserController::class, 'login']);


/*********************************IAM CONTROLLER*************************************** */
Route::get('list-users', [IAMController::class, 'listUsers']);

Route::get('check-login', [UserController::class, 'checkLogin'])->middleware(CheckTokenExpired::class);

Route::get('test', [TestController::class, 'test']);