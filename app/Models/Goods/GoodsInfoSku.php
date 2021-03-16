<?php

namespace App\Models\Goods;

use Illuminate\Database\Eloquent\Model;

class GoodsInfoSku extends Model
{
    protected $table = 'goods_info_sku';
    public $timestamps = false;

    protected $fillable = [
        'goods_id',                             // 商品id
        'erp_sku_id',                           // SKU_ID
        'sku',                                  // SKU SPU+"-"+barcode_seri_no
        'status',                               // 状态 0关闭1开启-1删除
        'option_values',                        // 属性
        'creator_id',                           // 创建人 0否1是
        'created_time',                         // 创建时间
        'deletor_id',                           // 删除人
        'deleted_time',                         // 删除时间
    ];
}
