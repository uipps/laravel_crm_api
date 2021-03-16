<?php

namespace App\Dto;

class CustomerAddressDto extends BaseDto
{
    public $id = 0;                             // 唯一id
    public $customer_id = 0;                    // 客户id
    public $customer_name = '';                 // 客户名称 最近使用名称
    public $language_id = 0;                    // 语言id
    public $language_name = '';
    public $country_id = 0;                     // 国家id
    public $country_name = '';
    public $zone_prov_name = '';                // 省/州id 对应地区id
    public $zone_city_name = '';                // 城市id 对应地区id
    public $zone_area_name = '';                // 区域id 对应地区id
    public $tel = '';                           // 电话号码
    public $address = '';                       // 详细地址
    public $zip_code = '';                      // 邮编
    public $email = '';                         // 邮箱
    public $source_type = 0;                    // 来源类别,1广告2咨询3复购
    public $quality_level = 0;                  // 客户质量,1A2B3C4D
}
