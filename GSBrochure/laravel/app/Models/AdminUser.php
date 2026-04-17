<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;

class AdminUser extends Model implements AuthenticatableContract
{
    use Authenticatable;

    protected $table = 'admin_users';

    protected $fillable = ['username', 'password_hash'];

    protected $hidden = ['password_hash'];

    public function getAuthPassword()
    {
        return $this->password_hash;
    }
}
