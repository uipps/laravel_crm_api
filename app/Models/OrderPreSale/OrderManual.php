<?php

namespace App\Models\OrderPreSale;

use App\Mappers\CommonMapper;
use App\Mappers\OrderMapper;
use App\Models\Admin\Department;
use App\Models\Admin\User;
use App\Models\Traits\HasActionTrigger;
use App\Models\Traits\HasOrderRelation;
use Auth;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class OrderManual extends Model
{
    use HasActionTrigger;
    use HasOrderRelation;

    const TYPE_NORMAL     = 1;  // 常规单
    const TYPE_REPLENISH  = 2;  // 补发
    const TYPE_REDELIVERY = 3;  // 重发

    protected $table = 'order_manual';
    public $timestamps = false;
    const CREATED_AT = 'created_time';
    const UPDATED_AT = 'updated_time';

    protected $fillable = [
        'part',                                 // 分区字段 order_sale_id按10取模
        'order_sale_id',                        // 订单客服id
        'department_id',                        // 部门id
        'order_id',                             // 订单id type=3时，显示原始订单
        'order_no',                             // 订单号 type=3时，显示原始订单
        'type',                                 // 类别 1常规单2补发单3重发单4线索
        'job_type',                             // 岗位类别 1售前2售后
        'source_order_id',                      // 原订单id
        'source_order_no',                      // 原订单号
        'remark',                               // 备注
        'audit_status',                         // 审核状态,0待审核1已审核-1已驳回
        'status',                               // 状态 0未提交1已提交-1已取消
        'opt_time',                             // 处理时间
        'creator_id',                           // 创建人
        'created_time',                         // 创建时间
        'updator_id',                           // 修改人
        'updated_time',                         // 更新时间
    ];

    protected $appends = ['status_manual'];

    public function getStatusManualAttribute()
    {
        return $this->status;
    }


    /**
     * 动态注册事件
     */
    public static function boot(){
        parent::boot();
        
        static::saved(function (Model $model) {
            $jobType = request()->input('request_job_type');
            $submitType =request()->input('submit_type');
            $preOptType = request()->input('pre_opt_type');
            $order = $model->order;

            $insert = [
                'order_id' => $model->source_order_id,
                'order_status' => $order->order_status,
                'month' => date("Ym"),
                'optator_id' => Auth('api')->id(),
                'remark' => '',
            ];

            $user = Auth('api')->user();
            // 售前主管
            if($jobType == CommonMapper::PRE_SALE && $user->level == CommonMapper::LEVEL_MANAGER && $submitType == CommonMapper::SUBMIT_OK){
                if($model->source_order_no){
                    if($model->type == OrderMapper::SECOND_TYPE_REPLENISH)
                    {
                        $insert['opt_type_id'] = 77;
                        $insert['remark'] = '补发订单是'.$model->order_no;
                    }elseif($model->type == OrderMapper::SECOND_TYPE_REDELIVERY)
                    {
                        $insert['opt_type_id'] = 82;
                        $insert['remark'] = '重发订单是'.$model->order_no;
                    }
                    OrderOptRecord::create($insert);
                }
            }
            
            // 售后客服
            if($jobType == CommonMapper::AFTER_SALE && $user->level == CommonMapper::LEVEL_SERVICE && $submitType == CommonMapper::SUBMIT_OK){
                if($model->source_order_no){
                    if($model->type == OrderMapper::SECOND_TYPE_REPLENISH)
                    {
                        $insert['opt_type_id'] = 77;
                        $insert['remark'] = '补发订单是'.$model->order_no;
                    }elseif($model->type == OrderMapper::SECOND_TYPE_REDELIVERY)
                    {
                        $insert['opt_type_id'] = 82;
                        $insert['remark'] = '重发订单是'.$model->order_no;
                    }

                    OrderOptRecord::create($insert);
                }

                
            }
        });
    }

    public function ab_redelivery_child(){
        // $relation = $this->hasMany(OrderManual::class, 'source_order_id', 'order_id');
        // dd($relation->getRelated()->getTable(), get_class_methods($relation));
        // $parentTable = $this->getParent()->getModel()->getTable();
        return $this->hasMany(OrderManual::class, 'source_order_id', 'order_id')->where('type', 5);
    }


    /**
     * 当前用户过滤部门 如果角色是主管，则查询部门
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeAftersalePemission(Builder $query)
    {
        $login_user_info = Auth('api')->user();

        if ($login_user_info['id'] > 1 && $login_user_info['level'] >0 ) {
            if (User::LEVEL_ADMIN == $login_user_info['level']) {
                // 主管能看到本部门的全部列表
                $dept_id = $login_user_info['department_id'];

                $all_departments = Department::get()->toArray();

                // 查询到部门下的所有子部门
                $staff_dept_list = \getAllChildIdByParentId($all_departments, $dept_id);
                if ($staff_dept_list) {
                    $staff_dept_list = array_column($staff_dept_list, 'id');
                }
                $staff_dept_list[] = $dept_id; // 加上本身所在部门

                return $query->whereIn('department_id', $staff_dept_list);

            } else if (User::LEVEL_STAFF == $login_user_info['level']) {
                // 员工只能看到自己的: 自己创建或分配给自己的
                return $query->where('order_sale_id', $login_user_info['id']);   // 分配给当前用户的
            }
        }

        return $query;
    }
}
