<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;

class UserWeight extends Model
{
    const STATUS_O = 0;
    const STATUS_1 = 1; // 启用

    protected $table = 'user_weight';
    public $timestamps = false;

    protected $fillable = [
        'user_id',                              // 员工id
        'language_id',                          // 语言id
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
