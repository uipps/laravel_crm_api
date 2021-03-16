<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;

class SysConfig extends Model
{
    protected $table = 'sys_config';
    public $timestamps = false;

    protected $fillable = [
        'conifg_code',                          // 配置编码
        'value_1',                              // 配置值1
        'value_2',                              // 配置值2
    ];
}
