<?php

namespace App\Dto;

class MainPanelDto extends BaseDto
{
    public $order_total = 0;                    // 总订单数
    public $order_finished = 0;                 // 已完成
    public $order_unfinished = 0;               // 未完成
    public $order_upsales = 0;                  // Upsales订单
    public $orderout_signed = 0;                // 已签收
    public $orderout_rejected = 0;              // 拒收
    public $orderout_delivering = 0;            // 未签收
    public $orderout_sign_rate = 0;             // 签收率
    public $orderout_by_country = [];           // 各个国家的签收情况
}
