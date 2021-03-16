<?php

namespace App\Models\Customer;

use Illuminate\Database\Eloquent\Model;

class CustomerRemark extends Model
{
    protected $table = 'customer_remark';
    public $timestamps = false;

    protected $fillable = [
        'customer_id',                          // 客户id
        'remark',                               // 备注
        'created_time',                         // 创建时间
        'updated_time',                         // 修改时间
        'creator_id',                           // 创建人
        'updator_id',                           // 修改人
    ];
}
