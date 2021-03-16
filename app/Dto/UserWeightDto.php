<?php

namespace App\Dto;

class UserWeightDto extends BaseDto
{
    public $id = 0;                             // 唯一id
    public $user_id = 0;                        // 员工id
    public $language_id = 0;                    // 语言id
    public $language_name = 0;                  // 语言名称
    public $weight = 0;                         // 权重
    public $ratio = 0;                          // 比例
    //public $status = 0;                         // 状态 0关闭1开启
    //public $remark = '';                        // 备注
    public $creator_id = 0;                     // 创建人
    public $updator_id = 0;                     // 修改人
    public $created_time = '';                  // 创建时间
    public $updated_time = '';                  // 更新时间
}
