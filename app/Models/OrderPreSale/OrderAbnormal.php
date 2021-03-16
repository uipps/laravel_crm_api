<?php

namespace App\Models\OrderPreSale;

use App\Models\Traits\HasActionTrigger;
use App\Models\Traits\HasOrderRelation;
use Illuminate\Database\Eloquent\Model;

class OrderAbnormal extends Model
{
    use HasActionTrigger;
    use HasOrderRelation;

    protected $table = 'order_abnormal';
    public $timestamps = false;

    protected $fillable = [
        'part',                                 // order_sale_id按10取模
        'order_sale_id',                        // 订单客服id
        'order_id',                             // 订单id
        'order_no',                             // 订单号
        'abnormal_type',                        // 异常类别
        'abnormal_remark',                      // 异常原因
        'service_remark',                       // 异常备注
        'status',                               // 状态 0未处理1已处理
        'job_type',                             // 岗位类别 1售前2售后
        'opt_time',                             // 处理时间
        'abnormal_time',                        // 异常时间
        'creator_id',                           // 创建人
        'created_time',                         // 创建时间
        'updator_id',                           // 修改人
        'updated_time',                         // 更新时间
    ];

    protected $appends = ['status_abnormal'];

    public function getStatusAbnormalAttribute()
    {
        return $this->status;
    }
}
