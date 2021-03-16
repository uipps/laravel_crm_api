<?php

namespace App\Dto;

class CurrencyDto extends BaseDto
{
    public $id = 0;                             // 唯一id
    public $name = '';                          // 名称
    public $code = '';                          // 编码
    public $symbol = '';                        // 符号
    public $symbol_type = 0;                    // 符号类型 1左2右
    public $exchange_rate = '';                 // 汇率
    public $created_time = '';                  // 创建时间
    public $updated_time = '';                  // 更新时间
}
