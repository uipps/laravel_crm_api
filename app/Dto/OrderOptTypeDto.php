<?php

namespace App\Dto;

class OrderOptTypeDto extends BaseDto
{
    public $id = 0;                             // 唯一id
    public $job_type = 0;                       // 岗位类型 1售前2售后
    public $name = '';                          // 名称
    public $en_name = '';                       // 英文名称
    public $status = 0;                         // 状态 0关闭1开启
}
