<?php

namespace App\Dto;

class SmsTemplateDto extends BaseDto
{
    public $id = 0;                             // id
    public $code = '';                          // 编码
    public $platform_type = 0;                  // 平台类别
    public $msg_type = 0;                       // 业务类别
    public $content = '';                       // 内容
    public $created_time = '';                  // 创建时间
    public $updated_time = '';                  // 修改时间
}
