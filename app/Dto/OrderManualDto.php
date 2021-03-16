<?php

namespace App\Dto;

class OrderManualDto extends BaseDto
{
    //public $id = 0;                             // 唯一id
    //public $part = 0;                           // 分区字段 order_sale_id按10取模
    public $order_sale_id = 0;                  // 订单客服id，区分售前和售后
    //public $department_id = 0;                  // 部门id
    //public $order_id = 0;                       // 订单id type=3时，显示原始订单
    //public $order_no = '';                      // 订单号 type=3时，显示原始订单
    public $type = 0;                           // 类别 1常规单2补发单3重发单4线索
    public $job_type = 0;                       // 岗位类别 1售前2售后
    public $source_order_id = 0;                // 原订单id
    public $source_order_no = '';               // 原订单号
    public $source_order_info = [];             // 原订单信息
    public $remark = '';                        // 备注
    public $status_manual = 1;                  // 状态 0未提交1已提交-1已取消
    public $opt_time = '';                      // 处理时间
    public $audit_status = 0;                   // 审核状态, 售后手工单需要审核，0待审核1已审核-1已驳回
    //public $creator_id = 0;                     // 创建人
    //public $created_time = '';                  // 创建时间
    //public $updator_id = 0;                     // 修改人
    //public $updated_time = '';                  // 更新时间
}
