<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;

class SmsSendRecord extends Model
{
    protected $table = 'sms_send_record';
    public $timestamps = false;

    protected $fillable = [
        'month',                                // 分区字段 发送时间取yyyyMM(UTC+8)
        'phone',                                // 手机号码
        'msg_id',                               // 短信唯一标识
        'api',                                  // 接口地址
        'api_param',                            // 接口参数
        'api_response',                         // 接口响应
        'platform_type',                        // 平台类别 1.253,2.yunpian
        'msg_type',                             // 业务类别
        'send_result',                          // 发送结果 1发送成功-1发送失败
        'receipt_status',                       // 回执状态 0未回执1接收成功-1接收失败
        'receipt_content',                      // 回执内容
        'content',                              // 内容
        'created_time',                         // 创建时间
        'updated_time',                         // 修改时间
    ];
}
