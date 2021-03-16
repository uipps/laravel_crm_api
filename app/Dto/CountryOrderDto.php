<?php

namespace App\Dto;

class CountryOrderDto extends BaseDto
{
    public $country_id = 0;                     // 国家ID
    //public $country_code = '';
    public $country_name = '';                  // 国家名称
    public $order_upsales = 0;                  // Upsales订单
    public $orderout_signed = 0;                // 已签收
    public $orderout_rejected = 0;              // 拒收
    public $orderout_delivering = 0;            // 未签收
    public $orderout_sign_rate = 0;             // 签收率
}
