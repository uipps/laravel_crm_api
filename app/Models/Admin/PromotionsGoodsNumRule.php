<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;

class PromotionsGoodsNumRule extends Model
{
    protected $table = 'promotions_goods_num_rule';
    public $timestamps = false;

    protected $fillable = [
        'promotion_id',                         // 活动id
        'min_num',                              // 最小数量
        'discount',                             // 折扣
        'status',                               // 状态 0停用1启用-1删除
        'display_order',                        // 显示顺序
        'creator_id',                           // 创建人
        'created_time',                         // 创建时间
        'updator_id',                           // 修改人
        'updated_time',                         // 更新时间
        'deleted_time',                         // 删除时间
    ];

    protected $appends = ['rule_id', 'rule_name'];

    public function promotion()
    {
        return $this->belongsTo(Promotions::class, 'promotion_id', 'id');
    }

    public function getRuleIdAttribute()
    {
        return $this->id;
    }

    public function getRuleNameAttribute()
    {
        return '满' .$this->min_num. '件总价' . rtrim(bcmul($this->discount,10,1), '. 0') . '折';
    }

}
