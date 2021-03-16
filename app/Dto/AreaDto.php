<?php

namespace App\Dto;

class AreaDto extends BaseDto
{
    public $id = 0;                             // 唯一id
    public $parent_id = 0;                      // 上级id
    public $country_id = 0;                     // 国家id
    public $code = '';                          // 编码
    public $name = '';                          // 名称
    public $type = 0;                           // 类别 1省/州2城市3区域
}
