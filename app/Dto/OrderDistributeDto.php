<?php

namespace App\Dto;

class OrderDistributeDto extends BaseDto
{
    //public $id = 0;                             // 唯一id
    //public $part = 0;                           // 分区字段 department_id按10取模
    public $distribute_user_id = 0;             // 分配人id
    public $pre_distribute_id = 0;              // 前置分配id
    //public $department_id = 0;                  // 部门id
    //public $order_id = 0;                       // 订单id
    //public $order_no = '';                      // 订单号
    public $job_type = 0;                       // 岗位类别 1售前2售后
    public $distributed_dep_id = 0;             // 被分配部门id
    public $distributed_user_id = 0;            // 被分配人id
    public $status = 0;                         // 状态 0无效1有效
    //public $distribute_status = 0;              // 分配状态 0未分配1已分配-1已撤销
    public $distribute_type = 0;                // 分配方式 0手动1自动
    public $repeat_flag = 0;                    // 重复单标识
    //public $created_time = '';                  // 创建时间
    public $distributed_time = '';              // 分配时间
    public $canceled_time = '';                 // 撤销时间
    //public $creator_id = 0;                     // 创建人
    //public $updator_id = 0;                     // 修改人
}
