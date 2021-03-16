<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;

class SysOptRecord extends Model
{
    const TYPE_CREATE = 1; // 操作类型：1新增,2编辑,3删除
    const TYPE_EDIT = 2;
    const TYPE_DELETE = 3;

    const MODULE_WAREHOUSE = 1; // module 操作模块 1仓库,2产品,3订单
    const MODULE_PRODUCT = 2;
    const MODULE_ORDER = 3;
    const MODULE_USER = 4;     // 用户操作

    protected $table = 'sys_opt_record';
    public $timestamps = false;

    protected $fillable = [
        'user_id',                              // 用户id
        'user_ip',                              // 用户ip
        'type',                                 // 操作类型 1新增,2编辑,3删除
        'module',                               // 操作模块 1仓库,2产品,3订单
        'title',                                // 操作标题
        'req_uri',                              // 请求地址
        'req_content',                          // 请求内容
        'opt_time',                             // 操作时间
    ];
}
