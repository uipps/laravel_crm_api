<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;

class Currency extends Model
{
    protected $table = 'sys_currency';
    public $timestamps = false;

    protected $fillable = [
        'country_simple_code',                  // 国家二字码
        'country_code',                         // 国家编码
        'name',                                 // 名称
        'code',                                 // 编码
        'symbol',                               // 符号
        'symbol_type',                          // 符号类型 1左2右
        'exchange_rate',                        // 汇率
        'min_unit',                             // 最小单位
        'created_time',                         // 创建时间
        'updated_time',                         // 更新时间
    ];
}
