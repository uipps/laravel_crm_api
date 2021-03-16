<?php

namespace App\Dto;

class GoodsDiscountDto extends BaseDto
{
    public $id = 0;                             // 唯一id
    public $goods_id = 0;                       // 商品id
    public $effect_num = 0;                     // 生效数量
    public $discount = 0;                       // 折扣
    public $creator_id = 0;                     // 创建人 0否1是
    public $created_time = '';                  // 创建时间
    public $deletor_id = 0;                     // 删除人
    public $deleted_time = '';                  // 删除时间
}
