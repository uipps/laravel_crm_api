<?php

namespace App\Models\OrderPreSale;

use App\Mappers\OrderMapper;
use App\Models\Traits\HasActionTrigger;
use App\Models\Traits\HasDepartment;
use App\Models\Traits\HasOrderRelation;
use Illuminate\Database\Eloquent\Model;

class OrderAudit extends Model
{
    use HasActionTrigger;
    use HasOrderRelation;
    use HasDepartment;

    protected $table = 'order_audit';
    public $timestamps = false;

    protected $guarded = ['id'];

    protected $fillable = [
        'part',                                 // 分区字段 adudit_user_id按10取模
        'audit_user_id',                        // 待审核人id
        'pre_distribute_id',                    // 前置分配记录id
        'department_id',                        // 部门id
        'order_id',                             // 订单id
        'order_no',                             // 订单号
        'job_type',                             // 岗位类别 1售前2售后
        'status',                               // 状态 0无效1有效
        'audit_status',                         // 审核状态 0未审核1已审核-1已驳回
        'repeat_flag',                          // 重复单标识
        'audit_result_id',                      // 审核结果 对应订单处理类别id,最近一次
        'audited_time',                         // 审核时间
        'created_time',                         // 创建时间
        'updated_time',                         // 修改时间
        'creator_id',                           // 创建人
        'updator_id',                           // 修改人
    ];

}
