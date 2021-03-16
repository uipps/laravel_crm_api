<?php

namespace App\Models\OrderPreSale;

use Illuminate\Database\Eloquent\Model;

class OrderCallRecord extends Model
{
    protected $table = 'order_call_record';
    public $timestamps = false;

    protected $fillable = [
        'month',                                // 分区字段 下单时间取yyyyMM(UTC+8)
        'order_id',                             // 订单id
        'optator_id',                           // 操作人id 对应用户表id
        'call_time',                            // 呼出时间
        'call_duration',                        // 呼出时长 秒
        'call_id',                              // 拨号id
        'tel',                                  // 电话号码
        'status',                               // 呼叫状态
        'call_file_url',                        // 通话内容音频
    ];
}
