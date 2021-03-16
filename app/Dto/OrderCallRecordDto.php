<?php

namespace App\Dto;

class OrderCallRecordDto extends BaseDto
{
    public $id = 0;                             // 唯一id
    public $month = 0;                          // 分区字段 下单时间取yyyyMM(UTC+8)
    public $order_id = 0;                       // 订单id
    public $optator_id = 0;                     // 操作人id 对应用户表id
    public $call_time = '';                     // 呼出时间
    public $call_duration = 0;                  // 呼出时长 秒
}
