<?php

namespace App\Models\OrderPreSale;

use App\Models\Traits\HasDepartment;
use App\Models\Traits\HasOrderRelation;
use Illuminate\Database\Eloquent\Model;

class OrderDistribute extends Model
{
    use HasOrderRelation;
    use HasDepartment;

    protected $table = 'order_distribute';
    public $timestamps = false;

    protected $fillable = [
        'part',                                 // 分区字段 department_id按10取模
        'distribute_user_id',                   // 分配人id
        'pre_distribute_id',                    // 前置分配id
        'department_id',                        // 部门id
        'order_id',                             // 订单id
        'order_no',                             // 订单号
        'job_type',                             // 岗位类别 1售前2售后
        'distributed_dep_id',                   // 被分配部门id
        'distributed_user_id',                  // 被分配人id
        'status',                               // 状态 0无效1有效
        'distribute_status',                    // 分配状态 0未分配1已分配-1已撤销
        'distribute_type',                      // 分配方式 0手动1自动
        'repeat_flag',                          // 重复单标识
        'created_time',                         // 创建时间
        'distributed_time',                     // 分配时间
        'canceled_time',                        // 撤销时间
        'creator_id',                           // 创建人
        'updator_id',                           // 修改人
    ];
}
