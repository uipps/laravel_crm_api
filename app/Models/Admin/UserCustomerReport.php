<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;

class UserCustomerReport extends Model
{
    protected $table = 'user_customer_report';
    public $timestamps = false;

    protected $fillable = [
        'date',                                 // 日期 yyyyMMdd
        'user_id',                              // 用户id
        'manager_id',                           // 主管id
        'department_id',                        // 部门id
        'country_id',                           // 国家id
        'user_type',                            // 用户类别 1售前2售后
        'customer_level',                       // 客户等级 1A2B3C4D
        'customer_num',                         // 客户数量
        'created_time',                         // 创建时间
        'creator_id',                           // 创建人
        'updated_time',                         // 修改时间
        'updator_id',                           // 修改人
    ];
}
