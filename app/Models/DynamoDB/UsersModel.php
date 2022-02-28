<?php

namespace App\Models\DynamoDB;

use Illuminate\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Kitar\Dynamodb\Model\Model;
use Tymon\JWTAuth\Contracts\JWTSubject;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;

class UsersModel extends Model implements JWTSubject, AuthenticatableContract
{
    use Authenticatable;

    
    protected $connection = 'dynamodb';
    protected $table = 'users';
    protected $primaryKey = 'UserName';
    protected $sortKey = 'Password';
    protected $fillable = [
        "UserARN", 
        "SecretKey", 
        "UserName", 
        "UserId",
        "PublicKey",
        "FullName",
        "Password"
    ];

    /**
     * Get the identifier that will be stored in the subject claim of the JWT.
     *
     * @return mixed
     */
    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    /**
     * Return a key value array, containing any custom claims to be added to the JWT.
     *
     * @return array
     */
    public function getJWTCustomClaims()
    {
        return [];
    }
}
