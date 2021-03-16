<?php

namespace App\Dto;

class OrderPromotionsGoodsDetailDto extends BaseDto
{
    public $id = 0;                             // 唯一id
    public $order_id = 0;                       // 订单id
    public $group_id = 0;                       // 分组id
    public $promotions_id = 0;                  // 优惠活动id
    public $order_detail_id = 0;                // 订单详情id
    public $creator_id = 0;                     // 创建人
    public $created_time = '';                  // 创建时间
    public $updator_id = 0;                     // 修改人
    public $updated_time = '';                  // 更新时间
}
