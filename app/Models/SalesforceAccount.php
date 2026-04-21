<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SalesforceAccount extends Model
{
    protected $table = 'SF_Account';

    protected $primaryKey = 'ID';

    public $timestamps = false;

    protected $fillable = [
        'account_ID',
        'Name',
        'GSKR_Billing_Address__c',
        'GSKR_Contract__c',
        'GSKR_Gts_Type__c',
    ];
}
