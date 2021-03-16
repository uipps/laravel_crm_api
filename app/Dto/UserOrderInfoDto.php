<?php

namespace App\Dto;

class UserOrderInfoDto extends BaseDto
{
    public $id = 0;                             // 唯一id
    public $user_id = 0;                        // 用户id
    public $order_type = 0;                     // 订单类别:11未分配订单,12已分配订单,21未审核订单,22已审核订单,23已驳回订单,3手工下单,4重复订单,5无效订单,61未处理异常订单,62已处理异常订单
    public $order_num = 0;                      // 订单数量
    public $created_time = '';                  // 创建时间
    public $creator_id = 0;                     // 创建人
    public $updated_time = '';                  // 修改时间
    public $updator_id = 0;                     // 修改人
    public $user_type = 0;                      // 用户类别,1售前2售后
}
