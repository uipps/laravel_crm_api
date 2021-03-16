<?php

namespace App\Models\Goods;

use Illuminate\Database\Eloquent\Model;

class GoodsInfo extends Model
{
    protected $table = 'goods_info';
    public $timestamps = false;

    protected $fillable = [
        'erp_product_id',                       // erp商品ID
        'product_name',                         // 商品名称
        'foreign_name',                         // 商品外文名称
        'internal_name',                        // 内部名称
        'pic_url',                              // 图片地址
        'sell_price',                           // 建议零售价
        'product_price',                        // 商品价格
        'spu_id',                               // SPU_ID
        'spu',                                  // SPU 与product表中的SPU一致
        'status',                               // 商品状态 0关闭1开启
        'creator_id',                           // 创建人 0否1是
        'created_time',                         // 创建时间
        'deletor_id',                           // 删除人
        'deleted_time',                         // 删除时间
    ];
}
