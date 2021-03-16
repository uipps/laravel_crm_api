<?php

namespace App\Dto;

class DepartmentDto extends BaseDto
{
    public $id = 0;                             // 唯一id
    public $parent_id = 0;                      // 上级id
    public $code = '';                          // code标识 标识业务含义
    public $name = '';                          // 名称
    public $status = 0;                         // 状态 0关闭1开启-1删除
    public $status_display = '';
    public $job_type = 0;                       // 部门类型 0无1售前2售后
    public $job_type_display = '';
    public $country_weight_ratio = [];          // 一对多，多个国家
    public $distribute_type = 0;                // 分配方式 0手动1自动
    public $distribute_type_display = '';       // 显示名称
    public $remark = '';                        // 备注
    public $creator_id = 0;                     // 创建人
    public $updator_id = 0;                     // 修改人
    public $deletor_id = 0;                     // 删除人
    public $created_time = '';                  // 创建时间
    public $updated_time = '';                  // 更新时间
    public $deleted_time = '';                  // 删除时间
    //public $department_staff_num = 0;           // 该部门下的员工数（非删除的都算），临时兼容，以后改为字段user_num_all_level
    public $user_num_self_level = 0;            // 本部门员工数（不包括子部门）
    public $user_num_all_level = 0;             // 该部门下的所有员工数（包括子部门，员工非删除状态的都算）

    public $level = 0;
    public $children = [];                      // 子节点
}
