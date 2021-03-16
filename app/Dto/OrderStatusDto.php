<?php

namespace App\Dto;

class OrderStatusDto extends BaseDto
{
    public $id = 0;                             // 唯一id
    public $parent_id = 0;                      // 上级id
    public $type = 0;                           // 类型 1订单状态2物流状态
    public $name = '';                          // 名称
    public $status = 0;                         // 状态 0关闭1开启
}
