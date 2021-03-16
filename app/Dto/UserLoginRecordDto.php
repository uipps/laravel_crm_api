<?php

namespace App\Dto;

class UserLoginRecordDto extends BaseDto
{
    public $id = 0;                             // 唯一id
    public $user_id = 0;                        // 用户id
    public $user_ip = '';                       // 用户ip
    public $token = '';                         // 用户令牌
    public $login_time = '';                    // 登录时间
}
