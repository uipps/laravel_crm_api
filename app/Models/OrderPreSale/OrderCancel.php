<?php

namespace App\Models\OrderPreSale;

use App\Models\Admin\User;
use App\Models\Traits\HasActionTrigger;
use App\Models\Traits\HasOrderRelation;
use Illuminate\Database\Eloquent\Model;

class OrderCancel extends Model
{
    use HasOrderRelation;
    use HasActionTrigger;

    protected $table = 'order_cancel';
    public $timestamps = false;

    protected $appends = ['source_order_id', 'status_cancel'];

    protected $fillable = [
        'job_type',                             // 岗位类别 1售前2售后
        'order_id',                             // 订单id
        'order_no',                             // 订单号
        'remark',                               // 备注
        'status',                               // 状态 0未提交1已提交 2已归档
        'opt_result',                           // 处理结果 0未处理1成功-1失败
        'opt_time',                             // 处理时间
        'creator_id',                           // 创建人
        'created_time',                         // 创建时间
        'updator_id',                           // 修改人
        'updated_time',                         // 更新时间
    ];

    protected $attributes = [
        'remark' => '',
    ];

    public function getSourceOrderIdAttribute()
    {
        return $this->order_id;
    }

    public function getStatusCancelAttribute()
    {
        return $this->status;
    }

}
