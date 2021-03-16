<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;

class UserLoginRecord extends Model
{
    protected $table = 'user_login_record';
    public $timestamps = false;

    protected $fillable = [
        'user_id',                              // 用户id
        'user_ip',                              // 用户ip
        'token',                                // 用户令牌
        'login_time',                           // 登录时间
    ];
}
