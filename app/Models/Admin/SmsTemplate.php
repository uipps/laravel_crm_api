<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;

class SmsTemplate extends Model
{
    protected $table = 'sms_template';
    public $timestamps = false;

    protected $fillable = [
        'code',                                 // 编码
        'platform_type',                        // 平台类别
        'msg_type',                             // 业务类别
        'content',                              // 内容
        'created_time',                         // 创建时间
        'updated_time',                         // 修改时间
    ];
}
