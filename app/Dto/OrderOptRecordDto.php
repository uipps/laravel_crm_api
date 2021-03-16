<?php

namespace App\Dto;

class OrderOptRecordDto extends BaseDto
{
    public $id = 0;                             // 唯一id
    //public $month = 0;                          // 分区字段 下单时间取yyyyMM(UTC+8)
    public $order_id = 0;                       // 订单id
    public $order_status = 0;                   // 订单状态
    public $opt_type_id = 0;                    // 订单处理类别id
    public $opt_type_name = '';
    public $remark = '';                        // 备注
    public $optator_id = 0;                     // 操作人id 对应用户表id,为null时表示系统操作
    public $opt_time = '';                      // 操作时间
}
