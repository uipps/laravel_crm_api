<?php

namespace App\Dto;

class OrderStatsPreSaleDto extends BaseDto
{
    public $order_num_total = 0;        // 订单数
    public $audit_no = 0;               // 未审核
    public $audit_yes = 0;              // 已审核
    public $distribute_no = 0;          // 未分配
    public $distribute_yes = 0;         // 已分配
    public $manul_order_num = 0;        // 手动下单数
    public $repeat_order_num = 0;       // 重复订单数
    public $invalid_order_num = 0;      // 无效订单数
    public $abnormal_order_num = 0;     // 异常订单数
    public $abnormal_no_dealwith = 0;   // 未处理异常订单数
    public $abnormal_dealwith = 0;      // 已处理异常订单数

    public $askforcancel_total = 0;     // 取消订单申请
    public $askforcancel_no_dealwith = 0; // 取消订单申请 - 待处理
    public $askforcancel_succ = 0;      // 取消订单申请 - 取消成功
    public $askforcancel_fail = 0;      // 取消订单申请 - 取消失败
    public $askforcancel_finish = 0;    // 取消订单申请 - 归档
}
