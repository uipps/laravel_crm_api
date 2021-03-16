<?php

namespace App\Models\Traits;

use App\Mappers\CommonMapper;
use App\Mappers\OrderMapper;
use App\ModelFilters\OrderRelationFilter;
use App\Models\Admin\User;
use App\Models\OrderPreSale\Order;
use App\Models\OrderPreSale\OrderAudit;
use App\Models\OrderPreSale\OrderInvalid;
use App\Models\OrderPreSale\OrderManual;
use EloquentFilter\Filterable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * @method static \Illuminate\Database\Eloquent\Builder registerEvent
 * @method static \Illuminate\Database\Eloquent\Builder joinOrderDepartment
 */
trait HasOrderRelation {
    use Filterable;
    use HasBase;

    public static function bootHasOrderRelation()
    {        
        // 售前处理

        // 保存订单触发的事件
        static::saved(function (Model $model) {
            if(request('request_job_type') == 1){
                if($model instanceof OrderAudit) {
                    if($model->isDirty('audit_status') && $model->audit_status == OrderMapper::MANUAL_AUDIT_DONE && 15 <= $model->audit_result_id && $model->audit_result_id <= 19) {
                        
                        $info = $model->toArray();
                        $info['invalid_type'] = 2;
                        
                        if($model->audit_result_id == 19) $info['invalid_type'] = 3;
        
                        $info['order_sale_id'] = Auth('api')->id();
                        $info['part'] = Auth('api')->id()%10;
                        $info['status'] = 1;
                        OrderInvalid::create($info);
                    }
                }
            }else{
                // 保存订单触发的事件
                static::saved(function (Model $model) {
                    if($model instanceof OrderManual){
                        if($model->isDirty('status') && $model->status == OrderMapper::SUBMIT_TYPE_DONE) {
                            $manual = $model->toArray();
                            OrderAudit::updateOrCreate(['order_id' => $model->order_id], $manual);
                        }
        
                        if($model->isDirty('audit_status') && $model->audit_status == OrderMapper::MANUAL_AUDIT_DONE) {
                            $manual = $model->toArray();
                            $manual['audit_user_id'] = Auth('api')->id();
                            OrderAudit::updateOrCreate(['order_id' => $model->order_id], $manual);
                        }
                    }
                    
                });
            }
            
        });

        static::creating(function (Model $model) {
            if(in_array('creator_id', $model->getFillable())){
                $model->creator_id = Auth('api')->id();
            }
        });
        static::updating(function (Model $model) {
            if(in_array('updator_id', $model->getFillable())){
                $model->updator_id = Auth('api')->id();
            }
        });
        // 保存订单触发的事件
        static::saving(function (Model $model) {
            if(in_array('order_sale_id', $model->getFillable())){
                $model->order_sale_id = $model->order_sale_id ?: Auth('api')->id();
                $model->part = $model->part ?: $model->order_sale_id%10;

            }

            if(in_array('audit_user_id', $model->getFillable())) {
                $model->audit_user_id = $model->audit_user_id ?: Auth('api')->id();
                $model->part = $model->part ?: $model->audit_user_id%10;
            }
            
            if(in_array('department_id', $model->getFillable())){
                $model->department_id = $model->department_id ?: Auth('api')->user()->department_id;
            }


            if(in_array('opt_time', $model->getFillable())){
                $model->opt_time = date('Y-m-d H:i:s');
            }
        });

    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function after_sale()
    {
        return $this->belongsTo(User::class, 'order_sale_id');
    }

    public function source_order()
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * https://github.com/Tucker-Eric/EloquentFilter
     *
     * 页面条件查询使用
     */
    public function modelFilter()
    {
        return $this->provideFilter(OrderRelationFilter::class);
    }

    public function scopeJoinOrderDepartment($query, $callback = null) {

        $table = $query->getModel()->getTable();
        $existsQuery = Order::departmentPemission()->whereRaw($table.'.order_id = order.id');
        if (!is_null($callback)){
            $callback($existsQuery);
        }

        $query->addWhereExistsQuery($existsQuery->getQuery());

        $user = Auth('api')->user();
        $fields = $query->getModel()->getFillable();
        if($user->level == 2 && in_array('order_sale_id', $fields)){
            $query->where('order_sale_id', $user->id);
        }
        

        return $query;
    }

    public function scopejoinOrderItem($query, $callback = null, $jobType = null) {
        $table = $query->getModel()->getTable();
        $query->whereExists(function($query) use($table, $callback, $jobType){
            $query->select(DB::raw(1))
            ->from('order')
            ->whereRaw($table.'.order_id = order.id');
    
            if (!is_null($jobType)){
                $query->where($table.'.job_type', $jobType);
            }
    
            if (!is_null($callback)){
                $query = $callback($query);
            }
        });
        

        return $query;
    }

}
