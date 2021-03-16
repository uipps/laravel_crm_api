<?php

namespace App\Libs\Utils;

use App\Mappers\OrderMapper;
use App\Models\OrderPreSale\Order;
use Auth;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\Eloquent\Model;

class BaseDataMq{
    const TYPE_AD_DISTRIBUTE = 11;
    const TYPE_AD_DISTRIBUTE_CANCEL = 12;  

    /**
     * @var \predis\Client
     */
    protected static $instance = null;
    protected static $queue_list = [];

    public static function redisInstance(){
        if(!self::$instance){
            $sentinel = Cache::store('redis_sentinel');
            //消息队列redis key没有前缀
            $sentinel->setPrefix('');
            self::$instance = $sentinel->connection();
        }

        return self::$instance;
    }



    // 更新操作写入队列
    public static function writeMq($pushData){
        $redisKey = 'crm_data_service:order.msg.topic';
        $data = [
            'type' => Arr::get($pushData, 'type'),
            'orderNo' => Arr::get($pushData, 'orderNo'),
            'distributeId' => Arr::get($pushData, 'distributeId'),
            'distributedId' => Arr::get($pushData, 'distributedId'),
            'distributedDepId' => Arr::get($pushData, 'distributedDepId'),
            'time' => Arr::get($pushData, 'time'),
            'userId' => Arr::get($pushData, 'userId', Auth('api')->user()->id),
            'realName' => Arr::get($pushData, 'realName', Auth('api')->user()->real_name),
            'email' => Arr::get($pushData, 'email', Auth('api')->user()->email),
            'remark' => Arr::get($pushData, 'remark'),
            'erpId' => Arr::get($pushData, 'erpId', Auth('api')->user()->erp_id),
        ];

        $jsonData = json_encode($data);
        $redis = self::redisInstance();
        $ret = $redis->lPush($redisKey, $jsonData);

        $info = [
            'result' => $ret,
            'redis_key' => $redisKey,
            'data' => $data
        ];
        
        Log::channel('base_mq')->info($info);

        return $ret;
    }


    //批量处理
    public static function batchHandleMq(){
        foreach(self::$queue_list as $model){
            self::handleMq($model);
        }
    }

    public static function storeMq(Model $model){
        self::$queue_list[] = $model;
    }


    public static function handleMq(Model $model){
        $table = $model->getTable();
        switch($table){
            case 'customer_distribute':
                
                $type = $model->status ? 11 : 12;

                $orderNo = Order::find($model->order_id)->order_no;
                $distributeId = $model->distribute_user_id;
                $distributedId = $model->distributed_user_id;
                $distributedDepId = $model->department_id;

                self::writeMq(compact('type', 'orderNo', 'distributeId', 'distributedId', 'distributedDepId'));
            break;
            case 'order_audit':
                if($model->audit_result_id >= 11 && $model->audit_result_id <= 14 && $model->audit_status == 1){
                    $type = 21;

                    $orderNo = $model->order_no;
                    self::writeMq(compact('type', 'orderNo'));

                }elseif($model->audit_result_id >= 15 && $model->audit_result_id <= 19 && $model->audit_status == 1){
                    $type = 20;

                    $orderNo = $model->order_no;
                    self::writeMq(compact('type', 'orderNo'));
                }
                

            break;
            case 'order_manual':
                if(intval($model->status) == 1){
                    if($model->job_type == 1){
                        $type = 22;
                    }else{
                        $type = 23;
                        if($model->audit_status == 1){
                            $type = 24;
                        }
                    }
                    
                    $orderNo = $model->order_no;
                    $data = compact('type', 'orderNo');
                    if ($model->type == OrderMapper::SECOND_TYPE_REDELIVERY) {
                        $data['time'] = $model->created_time;
                    }
                    self::writeMq($data);
                }             
            break;
            case 'order_repeat':
                if(intval($model->status) == -1){
                    $type = 31;
                    
                    $orderNo = $model->order_no;
                    self::writeMq(compact('type', 'orderNo'));
                }             
            break;
            case 'order_abnormal':
                if(intval($model->status) == 1){
                    $type = 32;
                    
                    $orderNo = $model->order_no;
                    self::writeMq(compact('type', 'orderNo'));
                }             
            break;
            case 'customer':
                if(intval($model->status) == 1){
                    $type = 33;
                    
                    $orderNo = $model->order_no;
                    self::writeMq(compact('type', 'orderNo'));
                }             
            break;
            case 'order_cancel':
                if($model->status == 1){
                    $type = 41;
                    
                    $orderNo = $model->order_no;
                    $time = $model->created_time;
                    $remark = $model->remark;
                    
                    self::writeMq(compact('type', 'orderNo', 'time', 'remark'));
                }           
            break;
            // case 'sys_department':
            //     if($model->distribute_type == 1){
            //         $type = 60;
                    
            //         self::writeMq(compact('type'));
            //     }           
            // break;
        }
    }

}