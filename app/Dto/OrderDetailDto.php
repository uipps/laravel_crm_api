<?php

namespace App\Dto;

class OrderDetailDto extends BaseDto
{
    public function Assign($items) {
        parent::Assign($items);
        if (isset($items['option_values']) && !is_array($items['option_values']) && $items['option_values'] && '[]' != $items['option_values']&& '"[]"' != $items['option_values'] && !is_array($items['option_values']))
            $this->option_values = json_decode($items['option_values'], true);

        if (isset($items['rule_ids']) && !is_array($items['rule_ids']) && '[]' != $items['rule_ids'] && '"[]"' != $items['rule_ids'])
            $this->rule_ids = json_decode($items['rule_ids'], true);
    }

    public $id = 0;                             // 唯一id
    //public $month = 0;                          // 分区字段 下单时间取yyyyMM(UTC+8)
    public $order_id = 0;                       // 订单id
    public $product_id = 0;                     // 产品id
    public $goods_id = 0;                       // 商品id 手工单,对应商品信息id
    public $spu = '';                           // SPU
    public $sku_id = 0;                         // SKU_ID
    public $sku = '';                           // SKU
    public $product_name = '';                  // 产品名称
    public $internal_name = '';                 // 内部名称
    public $option_values = [];                 // 商品属性
    public $en_name = '';                       // 英文名称
    public $pic_url = '';                       // 商品图片
    public $num = 0;                            // 数量
    public $unit_price = 0;                     // 单价
    public $total_amount = 0;                   // 总金额
    public $promotions_amount = 0;              // 优惠金额
    public $finish_amount = 0;                  // 优惠后金额
    public $rule_ids = [];                      // 参与的活动
    public $creator_id = 0;                     // 创建人
    public $created_time = '';                  // 创建时间
    public $updator_id = 0;                     // 修改人
    public $updated_time = '';                  // 修改时间
}
