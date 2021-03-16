<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;

class DepartmentWeight extends Model
{
    const STATUS_O = 0;
    const STATUS_1 = 1; // 启用

    protected $table = 'sys_department_weight';
    public $timestamps = false;

    protected $fillable = [
        'department_id',                        // 部门id
        'country_id',                           // 国家id
        'weight',                               // 权重
        'ratio',                                // 比例
        'status',                               // 状态 0关闭1开启
        'remark',                               // 备注
        'creator_id',                           // 创建人
        'updator_id',                           // 修改人
        'created_time',                         // 创建时间
        'updated_time',                         // 更新时间
    ];
}
