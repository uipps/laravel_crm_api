<?php

namespace App\Models\Customer;

use App\Models\Traits\HasActionTrigger;
use Illuminate\Database\Eloquent\Model;

class CustomerDistribute extends Model
{
    use HasActionTrigger;
    protected $table = 'customer_distribute';
    public $timestamps = false;

    protected $guarded = ['id'];

    protected $fillable = [
        'part',                                 // 分区字段 distribute_user_id按10取模
        'distribute_user_id',                   // 分配人id Range分区
        'pre_distribute_id',                    // 前置分配id
        'department_id',                        // 部门id
        'customer_id',                          // 客户id
        'tel',                                  // 客户电话
        'type',                                 // 类别 1售前2售后
        'distributed_user_id',                  // 被分配人id
        'distribute_type',                      // 分配方式 0手动1自动
        'status',                               // 状态 0未分配1已分配-1已撤销
        'created_time',                         // 创建时间
        'distributed_time',                     // 分配时间
        'canceled_time',                        // 撤销时间
        'creator_id',                           // 创建人
        'updator_id',                           // 修改人
    ];
}
