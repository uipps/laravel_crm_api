<?php

namespace App\Dto;

class UserAttrDto extends BaseDto
{
    public $id = 0;                             // 唯一id
    public $type = 0;                           // 类别 1语言2未定义3角色
    public $user_id = 0;                        // 用户id
    public $work_id = 0;                        // 业务id type=1对应语言id,type=2对应角色id
    public $created_time = '';                  // 创建时间
    public $creator_id = 0;                     // 创建人
}
