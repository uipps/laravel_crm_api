<?php

namespace App\Models\Goods;

use Illuminate\Database\Eloquent\Model;

class GoodsPrice extends Model
{
    protected $table = 'goods_price';
    public $timestamps = false;

    protected $fillable = [
        'goods_id',                             // 商品id
        'country_id',                           // 国家id
        'price',                                // 价格
        'creator_id',                           // 创建人 0否1是
        'created_time',                         // 创建时间
        'deletor_id',                           // 删除人
        'deleted_time',                         // 删除时间
    ];
}
