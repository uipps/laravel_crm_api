<?php

namespace App\Dto;

class PhoneCodePrefixDto extends BaseDto
{
    public $id = 0;                             // 唯一id
    public $country_id = 0;                     // 国家id
    public $prefix = '';                        // 前缀
}
