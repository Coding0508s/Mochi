<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SalesforceFile extends Model
{
    protected $table = 'SF_Files';

    protected $primaryKey = 'ID';

    public $timestamps = false;

    protected $fillable = [
        'fileName',
        'download_Cnt',
        'LastUpdate_Date',
        'User',
        'created_Date',
    ];
}
