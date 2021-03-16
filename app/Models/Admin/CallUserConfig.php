<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;

class CallUserConfig extends Model
{
    protected $table = 'call_user_config';
    public $timestamps = false;

    protected $fillable = [
        'user_id',                              // 用户id
        'country_id',                           // 国家id
        'account_id',                           // 账号id
        'status',                               // 状态 0无效1有效
        'created_time',                         // 创建时间
        'updated_time',                         // 修改时间
    ];
}
