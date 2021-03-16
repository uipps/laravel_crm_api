<?php

namespace App\Models\OrderPreSale;

use App\Models\Traits\HasOrderRelation;
use Illuminate\Database\Eloquent\Model;

class OrderInvalid extends Model
{
    use HasOrderRelation;

    protected $table = 'order_invalid';
    public $timestamps = false;

    protected $fillable = [
        'part',                                 // 分区字段 order_sale_id按10取模
        'order_sale_id',                        // 订单客服id
        'department_id',                        // 部门id
        'order_id',                             // 订单id
        'order_no',                             // 订单号
        'invalid_type',                         // 无效类别(原因) 0有效1系统判重2审核取消3审核重复
        'job_type',                             // 岗位类别 1售前2售后
        'creator_id',                           // 创建人
        'created_time',                         // 创建时间
        'updator_id',                           // 修改人
        'updated_time',                         // 更新时间
    ];

    // public static function boot()
    // {
    //     parent::boot();

    //     static::creating(function (Model $model) {
    //         if(in_array('creator_id', $model->getFillable())){
    //             $model->creator_id = Auth('api')->id();
    //         }
    //     });
    //     static::updating(function (Model $model) {
    //         if(in_array('updator_id', $model->getFillable())){
    //             $model->updator_id = Auth('api')->id();
    //         }
    //     });
    //     // 保存订单触发的事件
    //     static::saving(function (Model $model) {
    //         if(in_array('order_sale_id', $model->getFillable())){
    //             $model->order_sale_id = $model->order_sale_id ?: Auth('api')->id();
    //             $model->part = $model->part ?: $model->order_sale_id%10;

    //         }
            
    //         if(in_array('department_id', $model->getFillable())){
    //             $model->department_id = Auth('api')->user()->department_id;
    //         }

    //     });
    // }
}
