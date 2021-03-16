<?php

namespace App\Dto;

class SysConfigDto extends BaseDto
{
    public $id = 0;                             // 唯一id
    public $conifg_code = '';                   // 配置编码
    public $value_1 = '';                       // 配置值1
    public $value_2 = '';                       // 配置值2
}
