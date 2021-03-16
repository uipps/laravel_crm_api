<?php

namespace App\Models\Customer;

use Illuminate\Database\Eloquent\Model;

class CustomerClueDistribute extends Model
{
    protected $table = 'customer_clue_distribute';
    public $timestamps = false;

    protected $fillable = [
        'part',                                 // 分区字段 distribute_user_id按10取模
        'distribute_user_id',                   // 分配人id
        'pre_distribute_id',                    // 前置分配id
        'department_id',                        // 部门id
        'clue_id',                              // 线索id
        'distributed_user_id',                  // 被分配人id
        'status',                               // 状态 0未分配1已分配-1已撤销
        'created_time',                         // 创建时间
        'distributed_time',                     // 分配时间
        'canceled_time',                        // 撤销时间
        'creator_id',                           // 创建人
        'updator_id',                           // 修改人
    ];
}
