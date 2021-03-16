<?php

namespace App\Dto;

class CustomerServiceRelationDto extends BaseDto
{
    public $id = 0;                             // 唯一id
    public $part = 0;                           // 分区字段 department_id按10取模
    public $customer_id = 0;                    // 客户id
    public $service_id = 0;                     // 客服id 对应用户表id
    public $department_id = 0;                  // 部门id
    public $relation_type = 0;                  // 关系类型 1售前2售后
    public $status = 0;                         // 状态 0无效1有效
    public $created_time = '';                  // 创建时间
    public $updated_time = '';                  // 修改时间
    public $creator_id = 0;                     // 创建人
    public $updator_id = 0;                     // 修改人
}
