<?php

namespace App\Models\Customer;

use Illuminate\Database\Eloquent\Model;

class CustomerAddress extends Model
{
    protected $table = 'customer_address';
    public $timestamps = false;

    protected $fillable = [
        'customer_id',                          // 客户id
        'language_id',                          // 语言id
        'country_id',                           // 国家id
        'zone_prov_name',                       // 省/州
        'zone_city_name',                       // 城市
        'zone_area_name',                       // 区域
        'customer_name',                        // 客户名称
        'tel',                                  // 电话号码
        'address',                              // 详细地址
        'zip_code',                             // 邮编
        'email',                                // 邮箱
        'created_time',                         // 创建时间
        'updated_time',                         // 修改时间
        'creator_id',                           // 创建人
        'updator_id',                           // 修改人
    ];
}
