<?php

namespace App\Models\Customer;

use Illuminate\Database\Eloquent\Model;

class CustomerClueOrderRelation extends Model
{
    protected $table = 'customer_clue_order_relation';
    public $timestamps = false;

    protected $fillable = [
        'clue_id',                              // 线索id
        'order_id',                             // 订单id
        'status',                               // 状态 0无效1有效
        'created_time',                         // 创建时间
        'updated_time',                         // 修改时间
        'creator_id',                           // 创建人
        'updator_id',                           // 修改人
    ];
}
