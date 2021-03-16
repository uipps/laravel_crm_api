<?php

namespace App\Dto;

class GoodsInfoDto extends BaseDto
{
    public function Assign($items) {
        parent::Assign($items);

        if (isset($items['product_price']))
            $this->unit_price = $items['product_price'];
    }
    public $id = 0;                             // 唯一id
    public $erp_product_id = 0;                 // erp产品id
    public $product_name = '';                  // 商品名称
    public $foreign_name = '';                  // 商品外文名称
    public $internal_name = '';                 // 内部名称
    public $pic_url = '';                       // 图片地址
    public $sell_price = 0;                     // 建议零售价
    //public $product_price = 0;                  // 商品价格
    public $unit_price = 0;                     // 商品价格, 跟order_detail中的商品价格字段保持一致
    //public $spu_id = 0;                         // SPU_ID
    public $spu = '';                           // SPU 与product表中的SPU一致
    public $status = 0;                         // 商品状态 0关闭1开启
    public $sku_list = [];                           // SKU列表
    public $price_list = [];                         // 价格列表
    public $creator_id = 0;                     // 创建人 0否1是
    public $created_time = '';                  // 创建时间
    //public $deletor_id = 0;                     // 删除人
    //public $deleted_time = '';                  // 删除时间
}
