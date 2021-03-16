<?php

namespace App\Models\Customer;

use App\Models\Admin\User;
use App\Models\Traits\HasDepartment;
use Illuminate\Database\Eloquent\Model;

class CustomerServiceRelation extends Model
{
    use HasDepartment;
    protected $table = 'customer_service_relation';
    public $timestamps = false;

    protected $fillable = [
        'part',                                 // 分区字段 department_id按10取模
        'customer_id',                          // 客户id
        'service_id',                           // 客服id 对应用户表id
        'department_id',                        // 部门id
        'relation_type',                        // 关系类型 1售前2售后
        'status',                               // 状态 0无效1有效
        'created_time',                         // 创建时间
        'updated_time',                         // 修改时间
        'creator_id',                           // 创建人
        'updator_id',                           // 修改人
    ];

    /**
     * 动态注册事件
     */
    public static function boot(){
        parent::boot();

        static::creating(function ($model) {
            $user = User::find($model->service_id);
            $model->department_id = $user->department_id;
            $model->part = $user->department_id%10;
            $model->status = 1;
            $model->creator_id = Auth('api')->id();
        });
    }
}
