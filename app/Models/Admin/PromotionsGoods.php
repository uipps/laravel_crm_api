<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;

class PromotionsGoods extends Model
{
    protected $table = 'promotions_goods';
    public $timestamps = false;

    protected $fillable = [
        'promotion_id',                         // 活动id
        'sku',                                  // SKU
        'status',                               // 状态 0停用1启用-1删除
        'creator_id',                           // 创建人
        'created_time',                         // 创建时间
        'updator_id',                           // 修改人
        'updated_time',                         // 更新时间
        'deleted_time',                         // 删除时间
    ];
}
