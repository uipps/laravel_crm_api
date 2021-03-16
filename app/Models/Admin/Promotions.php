<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;

class Promotions extends Model
{
    protected $table = 'promotions';
    public $timestamps = false;

    protected $fillable = [
        'name',                                 // 活动名称
        'type',                                 // 活动类别 1数量折扣
        'rule_attr',                            // 规则类别 1可叠加2不叠加
        'rule_scope',                           // 规则范围 1所有商品2单个商品
        'goods_scope',                          // 商品范围 1全部商品2部分商品
        'status',                               // 状态 0停用1启用-1删除
        'begain_time',                          // 生效时间
        'end_time',                             // 失效时间
        'creator_id',                           // 创建人
        'created_time',                         // 创建时间
        'updator_id',                           // 修改人
        'updated_time',                         // 更新时间
        'deleted_time',                         // 删除时间
    ];

    protected $appends = ['promotion_goods_sku'];

    public function promotion_rules()
    {
        return $this->hasMany(PromotionsGoodsNumRule::class, 'promotion_id');
    }

    public function promotions_goods()
    {
        return $this->hasMany(PromotionsGoods::class, 'promotion_id');
    }

    public function getPromotionGoodsSkuAttribute()
    {
        $ret = $this->promotions_goods()->pluck('sku')->toArray();

        return $ret;
    }
}
