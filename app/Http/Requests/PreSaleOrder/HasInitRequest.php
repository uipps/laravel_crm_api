<?php

namespace App\Http\Requests\PreSaleOrder;

use App\Mappers\CommonMapper;
use App\Mappers\OrderMapper;
use App\Mappers\RouteMapper;
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
use Illuminate\Validation\Validator;

Trait HasInitRequest
{
    public function withValidator(Validator $validator)
    {
        if(!$validator->fails()){
            $level = Auth()->user()->level;
            $jobType = 1;

            $submitType = $this->input('submit_type', 2);
            $orderType = RouteMapper::getOrderType($jobType);
            // 订单类型各个表的status字段
            $statusArr = [
                1 => OrderMapper::SUBMIT_TYPE_DONE,
                2 => OrderMapper::SUBMIT_TYPE_PENDING
            ];
            // 奇怪，为何$this 不包含 request()
            request()->merge([
                'request_job_type' => $jobType, // 用于事件判断订单是售前还是售后
                'routeOrderType' => $orderType,
                'model' => RouteMapper::getModel($orderType, $jobType),
                // 创建的初始化数据
                'store_data' => [
                    'order_type' => 2,// 售前手工
                    'order_scope' => 1, // 内部单
                    'order_source' => 4, // crm对应的部分
                ],
                // 更新的初始化数据
                'update_data' => [

                ],
                // 创建或者更新的初始化数据
                'save_data' => [
                    'job_type' => $jobType,
                    'sale_remark' => $this->input('pre_opt_remark', $this->input('sale_remark', '')),
                    'remark' => $this->input('remark', ''),
                ],
            ]);
            
            // 创建手工单等
            if($this->has('submit_type')){
                // 接收1以上的数字
                if($submitType){
                    $this->inputMerge('save_data', [
                        'status' => Arr::get($statusArr, $this->input('submit_type', 2), 0)
                    ]);
                }

                // 主管提交手工单，则属于审核通过
                if($submitType == OrderMapper::SUBMIT_TYPE_DONE 
                && $level == CommonMapper::LEVEL_MANAGER 
                && in_array($orderType, ['manual','general','replenish_able','redelivery_able','abnormal_redelivery_able','replenish','redelivery','abnormal_redelivery','clue'])){
                    $this->inputMerge('save_data', [
                        'audit_status' => 1
                    ]);
                }
            }

            // 客服审单
            $preOptType = $this->input('pre_opt_type');
            if($preOptType){
                $this->inputMerge('save_data', [
                    'audit_result_id' => $preOptType,
                    'pre_opt_time' => date('Y-m-d H:i:s'),
                ]);
                // 提交下并且符合售前处理
                if($submitType ==OrderMapper::SUBMIT_TYPE_DONE && 11 <= $preOptType && $preOptType <= 19 ) {
                    $audit_status = 0;
                    $status = 0;
                    $invalid_status = 0;
                    $order_status = 6;

    
                    if( 11 <= $preOptType && $preOptType <= 14 ) {
                        // 售前审核状态
                        $audit_status = OrderMapper::MANUAL_AUDIT_DONE;
                        $status = 1;

                    }elseif( 15 <= $preOptType && $preOptType <= 19 ) {
                        $audit_status = OrderMapper::MANUAL_AUDIT_DONE;
                        $invalid_status = 2; //审核取消
                        if($preOptType == 19){
                            $invalid_status = 3;
                        }
                        $order_status = 29;
                    }
    
                    $this->inputMerge('save_data', [
                        'audit_status' => $audit_status, // 售前审核状态
                        'order_status' => $order_status,
                        'invalid_status' => $invalid_status,
                        'invalid_type' => $invalid_status,
                        'status' => $status,
                        'audit_time' => date('Y-m-d H:i:s'),
                        'audited_time' => date('Y-m-d H:i:s'),
                    ]);
                }else{
                    $this->inputMerge('save_data', [
                        'status' => OrderMapper::SUBMIT_TYPE_PENDING
                    ]);
                }
                
            }

            if( $orderType == 'distribute_not' )
            {
                $this->inputMerge('save_data', ['type' => OrderMapper::TYPE_AD]);
            }
            elseif( $orderType == 'distributed' )
            {
                $this->inputMerge('save_data', ['type' => OrderMapper::TYPE_AD]);
            }
            elseif( $orderType == 'general' )
            {
                $this->inputMerge('save_data', [
                    'order_second_type' => OrderMapper::SECOND_TYPE_GENERAL,
                    'type' => OrderMapper::SECOND_TYPE_GENERAL,
                ]);
            }
            elseif( $orderType == 'replenish' )
            {
                $this->inputMerge('save_data', [
                    'order_second_type' => OrderMapper::SECOND_TYPE_REPLENISH,
                    'type' => OrderMapper::SECOND_TYPE_REPLENISH,
                ]);
            }
            elseif( $orderType == 'redelivery' )
            {
                $this->inputMerge('save_data', [
                    'order_second_type' => OrderMapper::SECOND_TYPE_REDELIVERY,
                    'type' => OrderMapper::SECOND_TYPE_REDELIVERY,
                ]);
            }
            elseif( $orderType == 'askforcancel_able' )
            {
                $this->inputMerge('save_data', [
                    'opt_result' => OrderMapper::CANCLE_OPT_PENDING,
                ]);
            }
            elseif( $orderType == 'clue' )
            {
                $this->inputMerge('save_data', [
                    'order_second_type' => OrderMapper::SECOND_TYPE_CLUE,
                    'type' => OrderMapper::SECOND_TYPE_CLUE,
                ]);
            }

            $this->merge(request()->input());
        }
    }


    public function inputMerge($key, $array){
        request()->merge([
            $key => array_merge(request()->input($key), $array)
        ]);
    }

}
