<?php

namespace App\Dto;

class CustomerClueDistributeDto extends BaseDto
{
    public $id = 0;                             // 唯一id
    public $part = 0;                           // 分区字段 distribute_user_id按10取模
    public $distribute_user_id = 0;             // 分配人id
    public $pre_distribute_id = 0;              // 前置分配id
    public $department_id = 0;                  // 部门id
    public $clue_id = 0;                        // 线索id
    public $distributed_user_id = 0;            // 被分配人id
    public $status = 0;                         // 状态 0未分配1已分配-1已撤销
    public $created_time = '';                  // 创建时间
    public $distributed_time = '';              // 分配时间
    public $canceled_time = '';                 // 撤销时间
    public $creator_id = 0;                     // 创建人
    public $updator_id = 0;                     // 修改人
}
