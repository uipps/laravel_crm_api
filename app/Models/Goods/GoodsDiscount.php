<?php

namespace App\Models\Goods;

use Illuminate\Database\Eloquent\Model;

class GoodsDiscount extends Model
{
    protected $table = 'goods_discount';
    public $timestamps = false;

    protected $fillable = [
        'goods_id',                             // 商品id
        'effect_num',                           // 生效数量
        'discount',                             // 折扣
        'creator_id',                           // 创建人 0否1是
        'created_time',                         // 创建时间
        'deletor_id',                           // 删除人
        'deleted_time',                         // 删除时间
    ];
}
