<?php

namespace App\Mappers;

use App\Models\OrderPreSale\Order;
use App\Models\OrderPreSale\OrderAbnormal;
use App\Models\OrderPreSale\OrderAudit;
use App\Models\OrderPreSale\OrderCancel;
use App\Models\OrderPreSale\OrderDistribute;
use App\Models\OrderPreSale\OrderInvalid;
use App\Models\OrderPreSale\OrderManual;
use App\Models\OrderPreSale\OrderRepeat;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class RouteMapper {
    public static function orderAfterSale(){
        return [
            'manual' => [],
            'general' => [],
            'audit' => [],
            'audit_not' => [],
            'audited' => [],
            'reject' => [],
            'abnormal' => [],
            'abnormal_no_dealwith' => [],
            'abnormal_dealwith' => [],
            'replenish_able' => [],
            'redelivery_able' => [],
            'replenish' => [],
            'redelivery' => [],
            'abnormal_redelivery_able' => [],
            'abnormal_redelivery' => [],
            'original' => [],
            'askforcancel' => [],
            'askforcancel_no_dealwith' => [],
            'askforcancel_cancel_succ' => [],
            'askforcancel_cancel_fail' => [],
            'askforcancel_place_on' => [],
            'askforcancel_able' => [],
            'clue' => [],
        ];
    }

    public static function orderPreSale(){
        return [
            'advertise' => [],
            'manual' => [],
            'general' => [],
            'audit' => [],
            'audit_not' => [],
            'audited' => [],
            'distribute_not' => [],
            'distributed' => [],
            'repeat' => [],
            'invalid' => [],
            'reject' => [],
            'abnormal' => [],
            'abnormal_no_dealwith' => [],
            'abnormal_dealwith' => [],
            'replenish_able' => [],
            'redelivery_able' => [],
            'replenish' => [],
            'redelivery' => [],
            'abnormal_redelivery_able' => [],
            'abnormal_redelivery' => [],
            'original' => [],
            'askforcancel' => [],
            'askforcancel_no_dealwith' => [],
            'askforcancel_cancel_succ' => [],
            'askforcancel_cancel_fail' => [],
            'askforcancel_place_on' => [],
            'askforcancel_able' => [],
            'customer' => [],   // 客户的订单列表，某客户的订单列表
        ];
    }

    /**
     * 获取订单路由类型
     */
    public static function getOrderType($jobType = 2){
        $routeName = request()->route()->getName();
        $typeArr = [];
        if($jobType == 2){
            $typeArr = array_keys(self::orderAfterSale());
        } else if ($jobType == 1) {
            $typeArr = array_keys(self::orderPreSale());
        }

        foreach($typeArr as $type) {
            $need = '.'.$type.'.';

            if (strpos($routeName, $need) > 0) {
                return $type;
            }
        }

        return false;
    }


    public static function getModel($orderType, $jobType = 1, $userId = 0) {
        $orderSource = [4, 15];
        $user = Auth('api')->user();

        switch($orderType){
            case 'manual':
                return OrderManual::joinOrderDepartment()->where('job_type', $jobType);
            break;
            case 'advertise':
                $query = Order::departmentPemission()->with(['order_audit','order_distribute'])->where('order_type', OrderMapper::TYPE_AD)
                ->whereIn('order_source', $orderSource);
                $user = Auth('api')->user();
                if($user->level == 2){
                    // 客服只能看到已分配的
                    $query->where('pre_sale_id', $user->id);
                    $query->where('distribute_status', 1);
                }
                return $query;
            break;
            case 'original':
                return Order::query()->whereIn('order_source', $orderSource);
            break;
            case 'distribute_not':
                return OrderDistribute::departmentDistribute()->joinOrderItem(function($query) use($orderSource){
                    $query->where('order_type', 1);
                    $query->whereIn('order_source', $orderSource);
                })->where('distribute_status', 0)->where('job_type',$jobType)->where('repeat_flag', 0);
            break;
            case 'distributed':
                $query = OrderDistribute::departmentDistribute()->joinOrderItem(function($query)use($orderSource){
                    $query->where('order_type', 1);
                    $query->whereIn('order_source', $orderSource);
                })->where('distribute_status', 1)->where('job_type',$jobType)
                ->orderByDesc('distributed_time')->where('repeat_flag', 0);

                if(Auth('api')->user()->is_super){
                    $query->where('distributed_user_id','>', 0);
                }
                return $query;
            break;
            case 'repeat':   
                return OrderRepeat::joinOrderDepartment(function($query){
                    $beginDate = date('Y-m-d 00:00:00', strtotime('-3 day'));
                    $query->whereIn('order_type',[1,2]);
                    $query->where('status', 0);
                    $user = Auth('api')->user();
                    if($user->level == 2){
                        $query->where('pre_sale_id', $user->id);
                    }
                    // $query->where('order_time', '>', $beginDate);
                });
            break;
            case 'invalid':
                return OrderInvalid::joinOrderDepartment()->where('job_type', $jobType);
            break;
            case 'general':
                return OrderManual::joinOrderDepartment()->where('job_type', $jobType)->where('type', OrderMapper::SECOND_TYPE_GENERAL);
            break;
            case 'audit':
                if($user->level == 2 || $user->is_final_department){
                    return OrderAudit::departmentAudit()->where('job_type', $jobType)->where('repeat_flag', 0);
                }else{
                    return Order::departmentPemission()->whereIn('order_source', $orderSource)->where('order_type', 1)->whereNotExists(function($query){
                        $query->select(DB::raw(1))
                        ->from('order_distribute')
                        ->whereRaw('order_distribute.order_id = order.id')
                        ->where('repeat_flag', 1);
                    });
                }
                
            break;
            case 'audit_not':
                if($user->level == 2 || $user->is_final_department){
                    return OrderAudit::departmentAudit($userId)->where('job_type', $jobType)->where('audit_status', OrderMapper::MANUAL_AUDIT_PENDING)->where('repeat_flag', 0);
                }else{
                    return Order::departmentPemission()->whereIn('order_source', $orderSource)->where('order_type', 1)->where('audit_status', OrderMapper::MANUAL_AUDIT_PENDING)->whereNotExists(function($query){
                        $query->select(DB::raw(1))
                        ->from('order_distribute')
                        ->whereRaw('order_distribute.order_id = order.id')
                        ->where('repeat_flag', 1);
                    });
                }
                
            break;
            case 'audited':
                return OrderAudit::departmentAudit()->where('job_type', $jobType)->where('audit_status', OrderMapper::MANUAL_AUDIT_DONE)->where('repeat_flag', 0)->orderByDesc('audited_time');
            break;
            case 'reject':
                return OrderAudit::departmentAudit()->where('job_type', $jobType)->where('audit_status', OrderMapper::MANUAL_AUDIT_REJECT)->where('repeat_flag', 0);
            break;
            case 'aftersale_audit':
                return OrderManual::joinOrderDepartment()->where('job_type', $jobType)->where('status', OrderMapper::SUBMIT_TYPE_DONE);
            break;
            case 'aftersale_audit_not':
                return  OrderManual::joinOrderDepartment()->where('job_type', $jobType)->where('status', OrderMapper::SUBMIT_TYPE_DONE)->where('audit_status', OrderMapper::MANUAL_AUDIT_PENDING);
            break;
            case 'aftersale_audited':
                return OrderManual::joinOrderDepartment()->where('job_type', $jobType)->where('status', OrderMapper::SUBMIT_TYPE_DONE)->where('audit_status', OrderMapper::MANUAL_AUDIT_DONE)->orderByDesc('opt_time');
            break;
            case 'aftersale_reject':
                return OrderManual::joinOrderDepartment()->where('job_type', $jobType)->where('status', OrderMapper::SUBMIT_TYPE_DONE)->where('audit_status', OrderMapper::MANUAL_AUDIT_REJECT);
            break;
            case 'abnormal':
                return OrderAbnormal::joinOrderDepartment()->where('job_type', $jobType);
            break;
            case 'abnormal_no_dealwith':
                return OrderAbnormal::joinOrderDepartment()->where('job_type', $jobType)->where('status', OrderMapper::SUBMIT_TYPE_PENDING);
            break;
            case 'abnormal_dealwith':
                return OrderAbnormal::joinOrderDepartment()->where('job_type', $jobType)->where('status', OrderMapper::SUBMIT_TYPE_DONE);
            break;
            case 'replenish_able':
                $shippingStatus = 9; // 签收
                if($jobType == 2){
                    $query = OrderManual::joinOrderDepartment(function($query) use($shippingStatus){
                        $query->where('shipping_status', $shippingStatus);
                    })->where('job_type', $jobType)->where('status', OrderMapper::SUBMIT_TYPE_DONE);
                    if(Auth('api')->user()->level == 2){
                        $query->where('order_sale_id',  Auth('api')->id());
                    }
                }else{
                    $query = Order::departmentPemission()->where('shipping_status', $shippingStatus)->whereIn('order_source', $orderSource);
                    if(Auth('api')->user()->level == 2){
                        $query->where('pre_sale_id',  Auth('api')->id());
                    }
                }
                return $query;
            break;
            case 'redelivery_able':
                $shippingStatus = 8; // 配送中
                $orderStatus = 18; // 已出库
                if($jobType == 2){
                    $query = OrderManual::joinOrderDepartment(function($query) use($shippingStatus, $orderStatus){
                        $query->where('shipping_status', $shippingStatus);
                        $query->where('order_status', $orderStatus);
                    })->where('job_type', $jobType)->where('status', OrderMapper::SUBMIT_TYPE_DONE);
                    if(Auth('api')->user()->level == 2){
                        $query->where('order_sale_id',  Auth('api')->id());
                    }
                }else{
                    $query = Order::departmentPemission()
                    ->where('shipping_status', $shippingStatus)
                    ->where('order_status', $orderStatus)
                    ->whereIn('order_source', $orderSource);
                    if(Auth('api')->user()->level == 2){
                        $query->where('pre_sale_id',  Auth('api')->id());
                    }
                }
                return $query;
            break;
            case 'replenish':
                return OrderManual::joinOrderDepartment()->where('job_type', $jobType)->where('type', OrderMapper::SECOND_TYPE_REPLENISH);
            break;
            case 'redelivery':
                return OrderManual::joinOrderDepartment()->where('job_type', $jobType)->where('type', OrderMapper::SECOND_TYPE_REDELIVERY);
            break;
            case 'abnormal_redelivery_able':
                
                $shippingStatus = [OrderMapper::SHIP_DELIVERING, OrderMapper::SHIP_REJECTION]; // 配送中
                $orderStatus = 18; // 已出库
                if($jobType == 2){

                    $query = OrderManual::joinOrderDepartment(function($query) use($shippingStatus, $orderStatus){
                        $query->whereIn('shipping_status', $shippingStatus);
                        $query->where('order_status', $orderStatus);
                    })->where('job_type', $jobType)->where('status', OrderMapper::SUBMIT_TYPE_DONE)
                    ->whereDoesntHave('ab_redelivery_child.order', function($query){
                        // 如果存在订单不是终态，则不能继续创建异常重发单
                        $query->whereIn('shipping_status', [0, OrderMapper::SHIP_DELIVERING, OrderMapper::SHIP_UNONLINE]);
                    });
                    if(Auth('api')->user()->level == 2){
                        $query->where('order_sale_id',  Auth('api')->id());
                    }
                }else{
                    $query = Order::departmentPemission()
                    ->whereIn('shipping_status', $shippingStatus)
                    ->where('order_status', $orderStatus)
                    ->whereIn('order_source', $orderSource)
                    ->whereIn('order_type', [1, 2])
                    ->whereDoesntHave('ab_redelivery_child.order', function($query){
                        // 如果存在订单不是终态，则不能继续创建异常重发单
                        $query->whereIn('shipping_status', [0, OrderMapper::SHIP_DELIVERING, OrderMapper::SHIP_UNONLINE]);
                    });

                    if(Auth('api')->user()->level == 2){
                        $query->where('pre_sale_id',  Auth('api')->id());
                    }
                }
                return $query;
            break;
            case 'abnormal_redelivery':
                return OrderManual::joinOrderDepartment()->where('job_type', $jobType)->where('type', OrderMapper::SECOND_TYPE_ABNORMAL_REDELIVERY);
            break;
            case 'askforcancel':
                return OrderCancel::joinOrderDepartment()->where('job_type', $jobType)->whereIn('status', [OrderMapper::SUBMIT_TYPE_PENDING, OrderMapper::SUBMIT_TYPE_DONE]);
            break;
            case 'askforcancel_no_dealwith':
                return OrderCancel::joinOrderDepartment()->where('job_type', $jobType)->where('status', OrderMapper::SUBMIT_TYPE_DONE)->where('opt_result', OrderMapper::CANCLE_OPT_PENDING);
            break;
            case 'askforcancel_cancel_succ':
                return OrderCancel::joinOrderDepartment()->where('job_type', $jobType)->where('status', OrderMapper::SUBMIT_TYPE_DONE)->where('opt_result', OrderMapper::CANCLE_OPT_SUCCESS);
            break;
            case 'askforcancel_cancel_fail':
                return OrderCancel::joinOrderDepartment()->where('job_type', $jobType)->where('status', OrderMapper::SUBMIT_TYPE_DONE)->where('opt_result', OrderMapper::CANCEL_OPT_FAIL);
            break;
            case 'askforcancel_place_on':
                return OrderCancel::joinOrderDepartment()->where('job_type', $jobType)->where('status', OrderMapper::SUBMIT_TYPE_ARCHIEVE);
            break;
            case 'askforcancel_able':
                // 已审核的订单
                $ableQuery = Order::departmentPemission()->whereNotExists(function($query){
                    $query->select(DB::raw(1))
                    ->from('order_cancel')
                    ->whereRaw('order_cancel.order_id = order.id')
                    ->where('opt_result', 0);
                })->where('invalid_status', 0)->where('audit_status', 1)->whereIn('order_source', $orderSource);
                if($jobType == 1){
                    if(Auth('api')->user()->level == 2){
                        $ableQuery->where('order_type', 1);
                        $ableQuery->where('pre_sale_id',  Auth('api')->id());
                    }

                }
                if($jobType == 2){
                    $ableQuery->whereExists(function($query){
                        $query->select(DB::raw(1))
                        ->from('order_manual')
                        ->whereRaw('order_manual.order_id = order.id');
                        if(Auth('api')->user()->level == 2){
                            $query->where('order_sale_id', Auth('api')->id());
                        }
                    });
                }
                return $ableQuery;
            break;
            case 'clue':
                return OrderManual::joinOrderDepartment()->where('job_type', $jobType)->where('type', OrderMapper::SECOND_TYPE_CLUE);
            break;
            default:
                return null;
            break;
        }
    }
}
