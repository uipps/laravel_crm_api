<?php

namespace App\Models\OrderPreSale;

use App\Models\Traits\HasActionTrigger;
use App\Models\Traits\HasOrderRelation;
use Illuminate\Database\Eloquent\Model;

class OrderRepeat extends Model
{
    use HasActionTrigger;
    use HasOrderRelation;

    protected $table = 'order_repeat';
    public $timestamps = false;

    protected $guarded = ['id'];

    protected $fillable = [
        'month',                                // 分区字段 订单时间取yyyyMM(UTC+8)
        'order_id',                             // 订单id
        'order_no',                             // 订单号
        'repeat_id',                            // 重复单唯一标识id 对应重复订单编码id
        'status',                               // 状态 0未处理1有效-1无效
        'opt_time',                             // 处理时间
        'creator_id',                           // 创建人
        'created_time',                         // 创建时间
        'updator_id',                           // 修改人
        'updated_time',                         // 更新时间
    ];

    protected $appends = ['status_repeat'];

    public function getStatusRepeatAttribute()
    {
        return $this->status;
    }
}
