<?php

namespace App\Dto;

class OrderPromotionsGroupDto extends BaseDto
{
    public $id = 0;                             // 唯一id
    public $order_id = 0;                       // 订单id
    public $group_no = 0;                       // 分组编号
    public $creator_id = 0;                     // 创建人
    public $created_time = '';                  // 创建时间
    public $updator_id = 0;                     // 修改人
    public $updated_time = '';                  // 更新时间
}
