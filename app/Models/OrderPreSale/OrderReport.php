<?php

namespace App\Models\OrderPreSale;

use Illuminate\Database\Eloquent\Model;

class OrderReport extends Model
{
    protected $table = 'order_report';
    public $timestamps = false;

    protected $fillable = [
        'type',                                 // 类别
        'optator_id',                           // 订单数量 1未分配订单2已分配订单3未审核订单4已审核订单5手工下单6重复订单7无效订单8异常订单
        'updated_time',                         // 更新时间
    ];
}
