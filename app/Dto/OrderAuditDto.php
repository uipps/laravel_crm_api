<?php

namespace App\Dto;

class OrderAuditDto extends BaseDto
{
    //public $id = 0;                             // 唯一id
    //public $part = 0;                           // 分区字段 adudit_user_id按10取模
    public $aduit_user_id = 0;                 // 待审核人id
    public $pre_distribute_id = 0;              // 前置分配记录id
    //public $department_id = 0;                  // 部门id
    //public $order_id = 0;                       // 订单id
    //public $order_no = '';                      // 订单号
    public $job_type = 0;                       // 岗位类别 1售前2售后
    public $status = 0;                         // 状态 0无效1有效
    //public $audit_status = 0;                   // 审核状态 0未审核1已审核-1已驳回
    public $repeat_flag = 0;                    // 重复单标识
    public $audit_result_id = 0;                // 审核结果 对应订单处理类别id,最近一次
    public $audited_time = '';                  // 审核时间
    //public $created_time = '';                  // 创建时间
    //public $updated_time = '';                  // 修改时间
    //public $creator_id = 0;                     // 创建人
    //public $updator_id = 0;                     // 修改人
}
