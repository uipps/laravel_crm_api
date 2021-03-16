<?php

namespace App\Models\OrderPreSale;

use Illuminate\Database\Eloquent\Model;

class OrderPromotionsGoodsDetail extends Model
{
    protected $table = 'order_promotions_goods_detail';
    public $timestamps = false;

    protected $fillable = [
        'order_id',                             // 订单id
        'promotion_rule_ids',                   // 活动折扣id
        'order_detail_id',                      // 订单详情id
        'creator_id',                           // 创建人
        'created_time',                         // 创建时间
        'updator_id',                           // 修改人
        'updated_time',                         // 更新时间
    ];
}
