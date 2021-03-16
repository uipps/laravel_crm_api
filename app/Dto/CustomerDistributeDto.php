<?php

namespace App\Dto;

class CustomerDistributeDto extends BaseDto
{
    public $id = 0;                             // 唯一id
    public $part = 0;                           // 分区字段 distribute_user_id按10取模
    public $distribute_user_id = 0;             // 分配人id Range分区
    public $pre_distribute_id = 0;              // 前置分配id
    public $department_id = 0;                  // 部门id
    public $order_id = 0;                       // 客户id
    public $tel = '';                           // 客户电话
    public $type = 0;                           // 类别 1售前2售后
    public $distributed_user_id = 0;            // 被分配人id
    public $distribute_type = 0;                // 分配方式 0手动1自动
    public $status = 0;                         // 状态 0未分配1已分配-1已撤销
    public $created_time = '';                  // 创建时间
    public $distributed_time = '';              // 分配时间
    public $canceled_time = '';                 // 撤销时间
    public $creator_id = 0;                     // 创建人
    public $updator_id = 0;                     // 修改人
}
