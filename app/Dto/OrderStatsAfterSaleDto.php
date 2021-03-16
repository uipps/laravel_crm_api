<?php

namespace App\Dto;

class OrderStatsAfterSaleDto extends BaseDto
{
    public $manul_order_num = 0;        // 手动下单数

    public $audit_num_total = 0;        // 订单数(下面3个之和)
    public $audit_no = 0;               // 待审核、未审核
    public $audit_yes = 0;              // 已审核
    public $audit_reject = 0;           // 已驳回

    public $abnormal_order_num = 0;     // 异常订单数(下面2个之和)
    public $abnormal_no_dealwith = 0;   // 未处理异常订单数
    public $abnormal_dealwith = 0;      // 已处理异常订单数
}
