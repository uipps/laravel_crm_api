<?php

namespace App\Dto;

class OrderReportDto extends BaseDto
{
    public $id = 0;                             // 唯一id
    public $type = 0;                           // 类别
    public $optator_id = 0;                     // 订单数量 1未分配订单2已分配订单3未审核订单4已审核订单5手工下单6重复订单7无效订单8异常订单
    public $updated_time = '';                  // 更新时间
}
