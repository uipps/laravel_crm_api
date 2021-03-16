<?php

namespace App\Models\OrderPreSale;

use Illuminate\Database\Eloquent\Model;

class OrderStatus extends Model
{
    protected $table = 'order_status';
    public $timestamps = false;

    protected $fillable = [
        'parent_id',                            // 上级id
        'type',                                 // 类型 1订单状态2物流状态
        'name',                                 // 名称
        'status',                               // 状态 0关闭1开启
    ];
}
