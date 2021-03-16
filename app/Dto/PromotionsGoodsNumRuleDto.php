<?php

namespace App\Dto;

class PromotionsGoodsNumRuleDto extends BaseDto
{
    public function Assign($items) {
        parent::Assign($items);
        if (isset($items['id']))
            $this->rule_id = $items['id'];
    }

    public $rule_id = 0;                             // 唯一id
    public $promotion_id = 0;                   // 活动id
    public $min_num = 0;                        // 最小数量
    public $discount = 0;                       // 折扣
    public $rule_name = '';                     // 拼装的名称 "满3件总价7.0折"
    public $display_order = 10;                 // 显示顺序
    //public $status = 0;                         // 状态 0停用1启用-1删除
    //public $creator_id = 0;                     // 创建人
    //public $created_time = '';                  // 创建时间
    //public $updator_id = 0;                     // 修改人
    //public $updated_time = '';                  // 更新时间
    //public $deleted_time = '';                  // 删除时间
}
