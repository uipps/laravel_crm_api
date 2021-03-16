<?php

namespace App\Dto;

class UserOrderReportDto extends BaseDto
{
    public $id = 0;                             // 唯一id
    public $date = 0;                           // 日期 yyyyMMdd
    public $user_id = 0;                        // 用户id
    public $manager_id = 0;                     // 主管id
    public $department_id = 0;                  // 部门id
    public $country_id = 0;                     // 国家id
    public $user_type = 0;                      // 用户类别 1售前2售后
    public $order_total_num = 0;                // 总订单数(下单数)
    public $order_finished_num = 0;             // 已完成订单数
    public $order_unfinished_num = 0;           // 未完成订单数
    public $order_received_num = 0;             // 已签收订单数
    public $order_upsales_num = 0;              // Upsales订单数
    public $order_refused_num = 0;              // 拒收订单数
    public $order_unreceived_num = 0;           // 未签收订单数
    public $order_received_money = 0;           // 已签收订单金额
    public $created_time = '';                  // 创建时间
    public $creator_id = 0;                     // 创建人
    public $updated_time = '';                  // 修改时间
    public $updator_id = 0;                     // 修改人
}
