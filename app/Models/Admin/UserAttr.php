<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;

class UserAttr extends Model
{
    const TYPE_LANGUAGE = 1;    // 1-语言，多选
    const TYPE_ROLE = 3;        // 3-角色,(本版本是单选，产品已确认）

    protected $table = 'user_attr';
    public $timestamps = false;

    public $type_list = ['language', 'role']; // 当前的属性类型：1-语言，2-部门(在user表)，3-角色

    protected $fillable = [
        'type',                                 // 类别 1语言2未定义3角色
        'user_id',                              // 用户id
        'work_id',                              // 业务id type=1对应语言id,type=2对应角色id
        'created_time',                         // 创建时间
        'creator_id',                           // 创建人
    ];
}
