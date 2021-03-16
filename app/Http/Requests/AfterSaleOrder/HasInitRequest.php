<?php

namespace App\Http\Requests\AfterSaleOrder;

use App\Mappers\CommonMapper;
use App\Mappers\OrderMapper;
use App\Mappers\RouteMapper;
use App\Models\Customer\CustomerClue;
use App\Models\OrderPreSale\Order;
use App\Models\OrderPreSale\OrderAbnormal;
use App\Models\OrderPreSale\OrderCancel;
use App\Models\OrderPreSale\OrderManual;
use Illuminate\Support\Arr;
use Illuminate\Validation\Validator;

Trait HasInitRequest
{
    public function withValidator(Validator $validator)
    {
        if(!$validator->fails()){
            $level = Auth()->user()->level;
            $jobType = 2;

            $orderType = RouteMapper::getOrderType($jobType);
            // 订单类型各个表的status字段
            $statusArr = [
                1 => OrderMapper::SUBMIT_TYPE_DONE,
                2 => OrderMapper::SUBMIT_TYPE_PENDING
            ];

            request()->merge([
                'request_job_type' => $jobType, // 用于事件判断订单是售前还是售后
                'routeOrderType' => $orderType,
                'model' => RouteMapper::getModel($orderType, $jobType),
                // 创建的初始化数据
                'store_data' => [
                    'order_type' => 3,// 售前手工
                    'order_scope' => 1, // 内部单
                    'order_source' => 4, // crm对应的部分
                ],
                // 更新的初始化数据
                'update_data' => [
                ],
                // 创建或者更新的初始化数据
                'save_data' => [
                    'job_type' => $jobType,
                    'sale_remark' => $this->input('sale_remark', ''),
                    'remark' => $this->input('remark', ''),
                ],
            ]);

            // 客服创建手工单等
            if($this->has('submit_type')){
                $submitType = $this->input('submit_type');
                $this->inputMerge('save_data', [
                    'status' => Arr::get($statusArr, $this->input('submit_type', 2), 0)
                ]);
                // 被主管驳回后提交 客服提交之后变成待审核
                if($submitType == OrderMapper::SUBMIT_TYPE_DONE && $level == CommonMapper::LEVEL_SERVICE){
                    $this->inputMerge('save_data', [
                        'audit_status' => 0
                    ]);
                }

                // @todo 没用到 --- 主管提交手工单，则属于审核通过 
                if($submitType == OrderMapper::SUBMIT_TYPE_DONE 
                && $level == CommonMapper::LEVEL_MANAGER 
                && in_array($orderType, ['manual','general','replenish_able','redelivery_able','abnormal_redelivery_able','replenish','redelivery','abnormal_redelivery'])){
                    $this->inputMerge('save_data', [
                        'audit_status' => 1
                    ]);
                }
            }

            // 审核状态
            if($this->has('audit_status')){
                if($this->input('audit_status') == 1){
                    $this->merge([
                        'audit_time' => date("Y-m-d H:i:s"),
                        'audited_time' => date("Y-m-d H:i:s"),
                    ]);
                }
            }


            if( $orderType == 'general' )
            {
                $this->inputMerge('save_data', [
                    'order_second_type' => OrderMapper::SECOND_TYPE_GENERAL,
                    'type' => OrderMapper::SECOND_TYPE_GENERAL,
                ]);
            }
            elseif(in_array($orderType, ['audit','audit_not','audited','reject']))
            {
                $routeOrderType = 'aftersale_'.$orderType;
                request()->merge([
                    'model' => RouteMapper::getModel($routeOrderType, $jobType)
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
