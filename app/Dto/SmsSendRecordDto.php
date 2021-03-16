<?php

namespace App\Dto;

class SmsSendRecordDto extends BaseDto
{
    public $id = 0;                             // id
    public $month = 0;                          // 分区字段 发送时间取yyyyMM(UTC+8)
    public $phone = '';                         // 手机号码
    public $msg_id = '';                        // 短信唯一标识
    public $api = '';                           // 接口地址
    public $api_param = '';                     // 接口参数
    public $api_response = '';                  // 接口响应
    public $platform_type = 0;                  // 平台类别
    public $msg_type = 0;                       // 业务类别
    public $send_result = 0;                    // 发送结果 1发送成功-1发送失败
    public $receipt_status = 0;                 // 回执状态 0未回执1接收成功-1接收失败
    public $receipt_content = '';               // 回执内容
    public $content = '';                       // 内容
    public $created_time = '';                  // 创建时间
    public $updated_time = '';                  // 修改时间
}
