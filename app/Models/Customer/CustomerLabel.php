<?php

namespace App\Models\Customer;

use Illuminate\Database\Eloquent\Model;

class CustomerLabel extends Model
{
    const LABEL_TYPE_QUALITY = 5;   // 客户质量

    const LABEL_STYLE_SINGLE = 1;   // 单值标签
    const LABEL_STYLE_QUALITY = 2;  // 多值标签

    protected $table = 'customer_label';
    public $timestamps = false;

    protected $fillable = [
        'customer_id',                          // 客户id
        'label_type',                           // 标签类别 1新客户,2复购客户,3高签收客户,4高拒收客户,5客户等级
        'label_style',                          // 标签形式 1单值标签,2多值标签
        'label_value',                          // 标签值 label_style为2时有值,eg:客户等级:A,B,C,D
        'status',                               // 状态 0无效1有效
        'created_time',                         // 创建时间
        'updated_time',                         // 修改时间
        'creator_id',                           // 创建人
        'updator_id',                           // 修改人
    ];
}
