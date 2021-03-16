<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;

class PhoneCodePrefix extends Model
{
    protected $table = 'sys_phone_code_prefix';
    public $timestamps = false;

    protected $fillable = [
        'country_id',                           // 国家id
        'prefix',                               // 前缀
    ];
}
