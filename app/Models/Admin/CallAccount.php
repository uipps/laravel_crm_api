<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;

class CallAccount extends Model
{
    protected $table = 'call_account';
    public $timestamps = false;

    protected $fillable = [
        'platform_type',                        // 平台类别 1.3cx
        'account_info',                         // 账号信息
        'device_code',                          // 设备标识
        'status',                               // 状态 0无效1有效
        'created_time',                         // 创建时间
        'updated_time',                         // 修改时间
    ];
}
