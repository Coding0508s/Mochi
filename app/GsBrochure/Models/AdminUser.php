<?php

namespace App\GsBrochure\Models;

use Illuminate\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;

class AdminUser extends GsBrochureModel implements AuthenticatableContract
{
    use Authenticatable;

    protected $fillable = ['username', 'password_hash'];

    protected $hidden = ['password_hash'];

    public function getTable(): string
    {
        return $this->prefixedTable('admin_users');
    }

    public function getAuthPassword(): string
    {
        return (string) $this->password_hash;
    }
}
