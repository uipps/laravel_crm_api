<?php

namespace App\Dto;

class OrderRepeatDto extends BaseDto
{
    //public $id = 0;                             // 唯一id
    //public $month = 0;                          // 分区字段 订单时间取yyyyMM(UTC+8)
    //public $order_id = 0;                       // 订单id
    //public $order_no = '';                      // 订单号
    //public $repeat_id = 0;                      // 重复单唯一标识id 对应重复订单编码id
    public $status_repeat = 0;                  // 状态 0未处理1有效-1无效
    public $opt_time = '';                      // 处理时间
    //public $creator_id = 0;                     // 创建人
    //public $created_time = '';                  // 创建时间
    //public $updator_id = 0;                     // 修改人
    //public $updated_time = '';                  // 更新时间
}
