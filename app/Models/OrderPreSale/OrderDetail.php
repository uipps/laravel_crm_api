<?php

namespace App\Models\OrderPreSale;

use App\Models\Admin\PromotionsGoodsNumRule;
use App\Models\Admin\PromotionsHistory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

class OrderDetail extends Model
{
    protected $table = 'order_detail';
    public $timestamps = false;

    protected $fillable = [
        'month',                                // 分区字段 下单时间取yyyyMM(UTC+8)
        'order_id',                             // 订单id
        'product_id',                           // 产品id
        'goods_id',                             // 商品id 手工单,对应商品信息id
        'spu',                                  // SPU
        'sku_id',                               // SKU_ID
        'sku',                                  // SKU
        'product_name',                         // 产品名称
        'internal_name',                        // 内部名称
        'option_values',                        // 商品属性
        'en_name',                              // 英文名称
        'pic_url',                              // 商品图片
        'num',                                  // 数量
        'unit_price',                           // 单价
        'total_amount',                         // 总金额
        'promotions_amount',                    // 优惠金额
        'finish_amount',                        // 优惠后金额
        'rule_ids',                             // 活动规则id，json数组字符串
        'promotions_info',
        'creator_id',                           // 创建人
        'created_time',                         // 创建时间
        'updator_id',                           // 修改人
        'updated_time',                         // 修改时间
    ];

    protected $appends = ['promotions', 'detail_id'];

    public static function boot(){
        parent::boot();

        static::creating(function(Model $model){
            $model->month = date('Ym');
            $model->product_id = request('erp_product_id', 0);
        });

    }


    public function getOptionValuesAttribute($value)
    {  

        return !is_array($value) ? json_decode($value, true) : $value;
    }

    // 存json字符串
    public function setOptionValuesAttribute($value)
    {
        if(is_null($value)) $value = '';

        $this->attributes['option_values'] = is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : $value;
    }

    public function getRuleIdsAttribute($value)
    {

        return !is_array($value) ? json_decode($value, true) : $value;
    }

    // 存json字符串
    public function setRuleIdsAttribute($value)
    {
        if(is_null($value)) $value = '';
        
        $this->attributes['rule_ids'] = is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : $value;
    }

    public function getDetailIdAttribute()
    {

        return $this->id;
    }

    // @todo 要删除掉
    public function getPromotionsAttribute()
    {
        if(!$this->promotions_info) return '';
        $arr = json_decode($this->promotions_info, true);

        $historyIdArr = array_column($arr, 'history_id');
        $history = PromotionsHistory::whereIn('id', $historyIdArr)->get();

        $ret = [];
        foreach($history as $item) {
            if(!$item->promotions_detail) continue;

            $ret[] =  json_decode($item->promotions_detail, true);
        }

        return $ret;


        // $ruleIdArr = array_column($arr, 'rule_id');
        // $rules = PromotionsGoodsNumRule::whereIn('id', $ruleIdArr)->get();
        // foreach($rules as $rule){
        //     $rule->rule_id = $rule->id;
        //     $rule->rule_name = '满' .$rule->min_num. '件总价' . rtrim(bcmul($rule->discount,10,1), '. 0') . '折';
        // }
        // return $rules;

    }
}
