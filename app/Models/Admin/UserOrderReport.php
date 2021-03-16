<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;

class UserOrderReport extends Model
{
    protected $table = 'user_order_report';
    public $timestamps = false;

    protected $fillable = [
        'date',                                 // 日期 yyyyMMdd
        'user_id',                              // 用户id
        'manager_id',                           // 主管id
        'department_id',                        // 部门id
        'country_id',                           // 国家id
        'user_type',                            // 用户类别 1售前2售后
        'order_total_num',                      // 总订单数(下单数)
        'order_finished_num',                   // 已完成订单数
        'order_unfinished_num',                 // 未完成订单数
        'order_received_num',                   // 已签收订单数
        'order_upsales_num',                    // Upsales订单数
        'order_refused_num',                    // 拒收订单数
        'order_unreceived_num',                 // 未签收订单数
        'order_received_money',                 // 已签收订单金额
        'created_time',                         // 创建时间
        'creator_id',                           // 创建人
        'updated_time',                         // 修改时间
        'updator_id',                           // 修改人
    ];

    /**
     * 关联country
     */
    public function country()
    {
        return $this->belongsTo(Country::class);
    }
}
