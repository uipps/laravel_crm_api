<?php

namespace App\Dto;

class TimeoutConfigDto extends BaseDto
{
    public function Assign($item) {
        parent::Assign($item);
        if (isset($item['conifg_code']))
            $this->name = $item['conifg_code'];
        if (isset($item['value_1']))
            $this->timeout = $item['value_1'];
    }

    public $id = 1;                             // 唯一id
    public $name = '接单超时时间';               // 配置名称
    public $timeout = 20;                       // 时间
    //public $unit = '分钟';                       // 单位，分钟
}
