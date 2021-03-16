<?php

namespace App\Dto;

class CountryDto extends BaseDto
{
    public $id = 0;                             // 唯一id
    public $display_name = '';                  // 根据语言设置，显示的名称
    public $cn_name = '';                       // 中文名称
    public $en_name = '';                       // 英文名称
    public $simple_en_name = '';                // 英文简称
    public $code = '';                          // 编码
    public $phone_code = '';                    // 区号
    public $timezone_value = '';                // 时区值
    public $status = 0;                         // 状态
    public $web_code = '';                      // 前端标识
}
