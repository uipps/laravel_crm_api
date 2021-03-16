<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;

class Department extends Model
{
    const DISTRIBUTE_TYPE_0 = 0; // 手动
    const DISTRIBUTE_TYPE_1 = 1; // 自动

    const JOB_TYPE_NONE         = 0; // 岗位类型， 部门类型 0无1售前2售后
    const JOB_TYPE_PRE_SALE     = 1;
    const JOB_TYPE_AFTER_SALE   = 2;

    protected $table = 'sys_department';
    public $timestamps = false;

    protected $fillable = [
        'parent_id',                            // 上级id
        'code',                                 // code标识 标识业务含义
        'name',                                 // 名称
        'status',                               // 状态 0关闭1开启-1删除
        'job_type',                             // 部门类型 0无1售前2售后
        'distribute_type',                      // 分配方式 0手动1自动
        'remark',                               // 备注
        'creator_id',                           // 创建人
        'updator_id',                           // 修改人
        'deletor_id',                           // 删除人
        'created_time',                         // 创建时间
        'updated_time',                         // 更新时间
        'deleted_time',                         // 删除时间
    ];
}
