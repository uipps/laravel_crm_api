<?php

namespace App\Dto;

class UserCustomerReportDto extends BaseDto
{
    public $id = 0;                             // 唯一id
    public $date = 0;                           // 日期 yyyyMMdd
    public $user_id = 0;                        // 用户id
    public $manager_id = 0;                     // 主管id
    public $department_id = 0;                  // 部门id
    public $country_id = 0;                     // 国家id
    public $user_type = 0;                      // 用户类别 1售前2售后
    public $customer_level = 0;                 // 客户等级 1A2B3C4D
    public $customer_num = 0;                   // 客户数量
    public $created_time = '';                  // 创建时间
    public $creator_id = 0;                     // 创建人
    public $updated_time = '';                  // 修改时间
    public $updator_id = 0;                     // 修改人
}
