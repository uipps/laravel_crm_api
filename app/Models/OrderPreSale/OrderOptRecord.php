<?php

namespace App\Models\OrderPreSale;

use App\Models\Admin\User;
use Illuminate\Database\Eloquent\Model;

class OrderOptRecord extends Model
{
    protected $table = 'order_opt_record';
    public $timestamps = false;

    protected $fillable = [
        'month',                                // 分区字段 下单时间取yyyyMM(UTC+8)
        'order_id',                             // 订单id
        'order_status',                         // 订单状态
        'opt_type_id',                          // 订单处理类别id
        'remark',                               // 备注
        'optator_id',                           // 操作人id 对应用户表id,为0或-1时表示系统操作,为0的需要展示
        'opt_time',                             // 操作时间
    ];


    public function operation()
    {
        return $this->belongsTo(OrderOptType::class, 'opt_type_id');
    }

    public function operator()
    {
        return $this->belongsTo(User::class, 'optator_id');
    }
}
