<?php

namespace App\Dto;

class PromotionsDto extends BaseDto
{
    public $id = 0;                             // 唯一id
    public $name = '';                          // 活动名称
    public $type = 0;                           // 活动类别 1数量折扣
    public $rule_attr = 0;                      // 规则类别 1可叠加2不叠加
    public $rule_scope = 0;                     // 规则范围 1所有商品2单个商品
    public $goods_scope = 0;                    // 商品范围 1全部商品2部分商品
    public $status = 0;                         // 状态 0停用1启用-1删除
    public $promotion_rules = [];               // 活动规则列表
    public $promotion_goods = [];               // 活动商品列表
    public $promotion_goods_sku = [];               // sku组成的数组 - @jingbo专用
    public $begain_time = '';                   // 生效时间
    public $end_time = '';                      // 失效时间
    public $creator_id = 0;                     // 创建人
    public $created_time = '';                  // 创建时间
    public $updator_id = 0;                     // 修改人
    public $updated_time = '';                  // 更新时间
    public $deleted_time = '';                  // 删除时间
}
