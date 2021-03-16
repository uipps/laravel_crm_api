<?php

namespace App\Dto;

class DepartmentWeightDto extends BaseDto
{
    public $id = 0;                             // 唯一id
    public $department_id = 0;                  // 部门id
    public $department_name = '';               // 部门名称
    public $country_id = 0;                     // 国家id
    public $country_name = '';                  // 国家名称
    public $weight = 0;                         // 权重
    public $ratio = 0;                          // 比例
    public $status = 0;                         // 状态 0关闭1开启
    public $status_display = '';                // 显示名称
    public $remark = '';                        // 备注
    public $creator_id = 0;                     // 创建人
    public $updator_id = 0;                     // 修改人
    public $created_time = '';                  // 创建时间
    public $updated_time = '';                  // 更新时间
}
