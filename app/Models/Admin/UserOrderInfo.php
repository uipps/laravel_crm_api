<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;

class UserOrderInfo extends Model
{
    protected $table = 'user_order_info';
    public $timestamps = false;

    protected $fillable = [
        'user_id',                              // 用户id
        'order_type',                           // 订单类别:11未分配订单,12已分配订单,21未审核订单,22已审核订单,23已驳回订单,3手工下单,4重复订单,5无效订单,61未处理异常订单,62已处理异常订单
        'order_num',                            // 订单数量
        'created_time',                         // 创建时间
        'creator_id',                           // 创建人
        'updated_time',                         // 修改时间
        'updator_id',                           // 修改人
        'user_type',                            // 用户类别,1售前2售后
    ];
}
