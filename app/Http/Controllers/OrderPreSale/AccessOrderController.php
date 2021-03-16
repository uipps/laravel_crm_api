<?php
/**
 * OrderController
 * @author dev@xhat.com
 * @since 2020-03-10
 */
namespace App\Http\Controllers\OrderPreSale;

use App\Exceptions\Handler;
use App\Exceptions\InvalidException;
use App\Http\Controllers\CommonController;
use App\Http\Requests\PreSaleOrder\ArchiveRequest;
use App\Http\Requests\PreSaleOrder\AuditRequest;
use App\Http\Requests\PreSaleOrder\CommonRequest;
use App\Http\Requests\PreSaleOrder\RepeatRequest;
use App\Models\OrderPreSale\Order;
use App\Http\Requests\PreSaleOrder\StoreRequest;
use App\Http\Requests\PreSaleOrder\UpdateRequest;
use App\Http\Resources\OrderRelationResource;
use App\Libs\Utils\BaseDataMq;
use App\Mappers\CommonMapper;
use App\Mappers\OrderMapper;
use App\Mappers\RouteMapper;
use App\Models\Admin\Promotions;
use App\Models\Admin\PromotionsHistory;
use App\Models\Customer\Customer;
use App\Models\Customer\CustomerAddress;
use App\Models\Customer\CustomerServiceRelation;
use App\Models\OrderPreSale\OrderAbnormal;
use App\Models\OrderPreSale\OrderAttachment;
use App\Models\OrderPreSale\OrderAudit;
use App\Models\OrderPreSale\OrderCancel;
use App\Models\OrderPreSale\OrderDetail;
use App\Models\OrderPreSale\OrderDistribute;
use App\Models\OrderPreSale\OrderInvalid;
use App\Models\OrderPreSale\OrderManual;
use App\Models\OrderPreSale\OrderRepeat;
use Cache;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class AccessOrderController extends CommonController
{
    public function __construct()
    {
        // 多表操作保证原子性，封装事务 先写入缓冲，等事务提交才执行写入队列
        $this->middleware('transact')->only(['store', 'update', 'replenishAbleUpdate', 'abnormalRedeliveryAbleUpdate']);
    }

    public function index(CommonRequest $request)
    {
        // $num = '0.85';
        // $ret = rtrim(bcmul($num,10,1), '.0');
        // dd($ret);

        $routeOrderType = $request->input('routeOrderType');

        /**
         * @var Builder|QueryBuilder $query
         */
        $query = $request->input('model'); //创建query对象

        if($query->getModel() instanceof Order){
            $with = [
                'goods_info',
                'country',
                'language',
                'pre_sale',
                'pre_opt',
            ];

        }else{
            $with = [
                'order',
                'order.goods_info',
                'order.country',
                'order.language',
                'order.pre_sale',
                'order.pre_opt',
            ];
        }


        $query->with($with);
        $query->filter($request->input());

        $ret = $query->dealList()->paginateFilter($request->input('limit'));
        // 添加统计数据
        if(!$request->input('no_meta')){
            request()->merge(['meta' => $this->order_stats()]);
        }
        if($routeOrderType == 'distribute_not'){
            $user =  Auth('api')->user();
            $status = CommonMapper::BTN_DISTRIBUTE_NONE;

            if($user->level == CommonMapper::LEVEL_SERVICE || $user->is_super || !$user->is_final_department){
                $status = CommonMapper::BTN_DISTRIBUTE_AUTH;
            }else{
                $redisHandle = Cache::store('redis_sentinel')->connection();
                $process = $redisHandle->hget('crm_data_service:distribute.task.bucket', $user->department_id);

                if($process){
                    $status = CommonMapper::BTN_DISTRIBUTE_PROCESS;
                }elseif($ret->total()){
                    $status = CommonMapper::BTN_DISTRIBUTE_PENDING;
                }
            }
            // 分单状态
            request()->merge(['distribute_btn_status' => $status]);
        }

        $ret = OrderRelationResource::collection($ret)->resource;
        
        return $this->wrapResponse($ret);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(storeRequest $request)
    {
        $saveData = $request->input('save_data');
        $storeData = $request->input('store_data');
        $request->merge($saveData);
        $request->merge($storeData);

        if($request->input('customer_name') && $request->input('country_id') && $request->input('country_id') && $request->input('submit_type') == CommonMapper::SUBMIT_OK){
            // 客户唯一key
            $customerKey = md5($request->input('country_id').$request->input('tel'));

            $customer = Customer::firstOrCreate(['customer_key' => $customerKey],$request->input());

            $request->merge([
                'customer_id' => $customer->id,
            ]);

            // 客服和客户关系表
            $attr = [
                'customer_id' => $customer->id,
                'service_id' => Auth('api')->id(),
            ];
            CustomerServiceRelation::firstOrCreate($attr, ['relation_type' => 1]);

            // 如果地址相关的信息不存在，则创建一条新记录
            $attr = $this->getCustomerAddressAttr($request);
            CustomerAddress::firstOrCreate($attr);
        }
        
        $query = $request->input('model'); //创建query对象

        $order = Order::create($request->input());

        $fileUrl = $request->input('attachment.file_url');
        if($fileUrl){
            $attr = [
                'order_id' => $order->id
            ];
            OrderAttachment::updateOrCreate($attr, ['file_url' => $fileUrl]);
        }

        $request->merge([
            'order_no' => $order->order_no,
            'order_id' => $order->id,
        ]);

        $OrderDetail = $request->input('goods_info', []);
        $detailArr = array_filter($OrderDetail, function($item){
            return $item['num'] > 0;
        });

        // foreach($detailArr as &$detail){
        //     //保存promotions_info
        //     $rules = Arr::get($detail, 'rules', []);
        //     $promotionsInfo = $this->getPromotionsInfo($rules);
        //     $detail['promotions_info'] = $promotionsInfo;
        // }

        if($detailArr){
            $order->goods_info()->createMany($detailArr);
        }

        $model = $query->create($request->input());

        return $this->wrapResponse($model);
    }

    public function getCustomerAddressAttr($request){
        $attr = [
            'customer_id' => $request->input('customer_id'),
            'language_id' => $request->input('language_id'),
            'country_id' => $request->input('country_id'),
            'zone_prov_name' => $request->input('zone_prov_name'),
            'zone_city_name' => $request->input('zone_city_name'),
            'zone_area_name' => $request->input('zone_area_name'),
            'customer_name' => $request->input('customer_name'),
            'tel' => $request->input('tel'),
            'address' => $request->input('address'),
            'zip_code' => $request->input('zip_code'),
        ];

        return $attr;
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show(CommonRequest $request, $id)
    {
        $routeOrderType = $request->input('routeOrderType');
        /**
         * @var Builder|QueryBuilder $query
         */
        $query = $request->input('model'); //创建query对象

        $model = $query->findOrFail($id);

        if($query->getModel() instanceof Order){
            $with = [
                'goods_info',
                'country',
                'language',
                'pre_sale',
                'pre_opt',
                'attachment',
            ];

            // 统一订单详情
            $child = OrderManual::where('source_order_id', $id)->orderByDesc('id')->first();
            $model->replenish_redelivery_status = 0;
            $model->replenish_redelivery_order_no = '';

            if($child){
                $statusArr = OrderMapper::getReplenishRedeliveryStatus();

                // 补重状态 0无 1-已补发 2-已重发 3-异常重发
                $model->replenish_redelivery_status = Arr::get($statusArr, $child->type, 0);
                $model->replenish_redelivery_order_no = $child->order_no;
            }

        }else{
            $with = [
                'order',
                'order.goods_info',
                'order.country',
                'order.language',
                'order.pre_sale',
                'order.pre_opt',
                'order.attachment'
            ];
            //如果有原订单
            if($model->source_order_id){
                $with = array_merge($with, [
                    'source_order',
                    'source_order.goods_info',
                ]);
            }

        }

        $model->load($with);

        $ret = new OrderRelationResource($model);

        return $this->wrapResponse($ret);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(UpdateRequest $request, $id)
    {
        // dd($request->input());
        $saveData = $request->input('save_data');
        $updateData = $request->input('update_data');
        $request->merge($saveData);
        $request->merge($updateData);

        $query = $request->input('model'); //创建query对象
        $model = $query->findOrFail($id);

        $OrderDetail = $request->input('goods_info', []);

        $routeOrderType = $request->input('routeOrderType');
        if($routeOrderType == 'redelivery_able'){
            $order = $model;
            $order->update([
                'order_second_type' => 3,
            ]);

            $attr = [
                'type' => 3,
                'order_id' => $order->id,
            ];

            $request->merge([
                'source_order_id' => $order->id,
                'source_order_no' => $order->order_no,
                'order_no' => $order->order_no.'_'.\createOrderNoSequenceNo($order->order_no),
            ]);

            OrderManual::updateOrCreate($attr,  $request->input());
        }elseif($routeOrderType == 'askforcancel_able'){
            $order = $model;
            $request->merge([
                'order_id' => $order->id,
                'order_no' => $order->order_no,
                'created_time' => date('Y-m-d H:i:s'),
            ]);

            $ret = OrderCancel::where('order_id', $order->id)->where('opt_result',0)->first();
            if($ret){
                throw new InvalidException("order cancel has been ask for!");
            }

            OrderCancel::create($request->input());
        }else{
            // 审核状态
            if($request->has('audit_status')){
                if($request->input('audit_status') == 1){
                    $request->merge([
                        'audit_time' => date("Y-m-d H:i:s"),
                        'audited_time' => date("Y-m-d H:i:s"),
                    ]);
                }
            }

            if($request->input('customer_name') && $request->input('country_id') && $request->input('country_id') && $request->input('submit_type') == CommonMapper::SUBMIT_OK){
                // 客户唯一key
                $customerKey = md5($request->input('country_id').$request->input('tel'));
                $request->merge([
                    'pre_sale_id' => $model->order->pre_sale_id,
                ]);
                
                $customer = Customer::firstOrCreate(['customer_key' => $customerKey],$request->input());

                $request->merge([
                    'customer_id' => $customer->id,
                ]);

                // 客服和客户关系表
                $attr = [
                    'customer_id' => $customer->id,
                    'service_id' => $model->order->pre_sale_id,
                ];
                CustomerServiceRelation::firstOrCreate($attr, ['relation_type' => 1]);

                // 如果地址相关的信息不存在，则创建一条新记录
                $attr = $this->getCustomerAddressAttr($request);
                CustomerAddress::firstOrCreate($attr);
            }
            

            $model->order->update($request->input());
            $fileUrl = $request->input('attachment.file_url');
            if($fileUrl){
                $attr = [
                    'order_id' => $model->order->id
                ];
                OrderAttachment::updateOrCreate($attr, ['file_url' => $fileUrl]);
            }

            //已经审核通过，不能退回到保存状态
            if($request->has('status') && $model->status == 1 && $request->input('status') == 0){
                $request->merge(['status' => 1]);
            }
            
            $model->update($request->input());

            if($request->has('goods_info')){
                $OrderDetail = $request->input('goods_info', []);

                $model->order->goods_info()->delete();
    
                foreach($OrderDetail as $detail){
                    //存在order_id表示来自order_detail表
                    // $detail_id = Arr::get($detail,'order_id') ? Arr::get($detail,'id') : false;
                    //保存promotions_info
                    $rules = Arr::get($detail, 'rules', []);
                    $promotionsInfo = $this->getPromotionsInfo($rules);
                    $detail['promotions_info'] = $promotionsInfo;
                    
                    // if($detail_id){
                    //     OrderDetail::find($detail_id)->update($detail);
                    // }else{
                    //     $detail['order_id'] = $model->order_id;
                    //     OrderDetail::create($detail);
                    // }
    
                    $detail['order_id'] = $model->order_id;
                        OrderDetail::create($detail);
                    
                }
            }

        }

        return $this->wrapResponse($model);
    }

    // 创建补发单
    public function replenishAbleUpdate(UpdateRequest $request, $id)
    {
        $saveData = $request->input('save_data');
        $updateData = $request->input('update_data');
        $request->merge($saveData);
        $request->merge($updateData);

        $query = RouteMapper::getModel('replenish_able');
        $order = $query->findOrFail($id);

        $OrderDetail = $request->input('goods_info', []);
        $newOrderArr = $order->toArray();

        $newOrderArr['order_second_type'] = 2; // 补发单
        $newOrderArr['order_type'] = OrderMapper::TYPE_PRE_SALE; // 售前手工

        $newOrder = Order::create($newOrderArr);
        $request->merge([
            'order_id' => $newOrder->id,
            'order_no' => $newOrder->order_no,
            'type' => 2, //补发单
            'source_order_id' => $order->id,
            'source_order_no' => $order->order_no,
        ]);


        OrderManual::create($request->input());

        $OrderDetail = $request->input('goods_info', []);

        $OrderDetail = array_filter($OrderDetail, function($item){
            return $item['num'] > 0;
        });

        foreach($OrderDetail as $detail){
            $detail['order_id'] = $newOrder->id;
            OrderDetail::create($detail); 
        }

        return $this->wrapResponse($newOrder);
    }

    // 创建异常重发单
    public function abnormalRedeliveryAbleUpdate(UpdateRequest $request, $id)
    {
        $saveData = $request->input('save_data');
        $updateData = $request->input('update_data');
        $request->merge($saveData);
        $request->merge($updateData);

        $query = RouteMapper::getModel('abnormal_redelivery_able');
        $order = $query->findOrFail($id);

        $OrderDetail = $request->input('goods_info', []);
        $newOrderArr = $order->toArray();

        $newOrderArr['order_second_type'] = OrderMapper::SECOND_TYPE_ABNORMAL_REDELIVERY; // 异常重发单
        $newOrderArr['order_type'] = OrderMapper::TYPE_PRE_SALE; // 售前手工

        $newOrder = Order::create($newOrderArr);
        $request->merge([
            'order_id' => $newOrder->id,
            'order_no' => $newOrder->order_no,
            'type' => OrderMapper::SECOND_TYPE_ABNORMAL_REDELIVERY, //异常重发单
            'source_order_id' => $order->id,
            'source_order_no' => $order->order_no,
        ]);


        OrderManual::create($request->input());

        $OrderDetail = $request->input('goods_info', []);

        $OrderDetail = array_filter($OrderDetail, function($item){
            return $item['num'] > 0;
        });

        foreach($OrderDetail as $detail){
            $detail['order_id'] = $newOrder->id;
            OrderDetail::create($detail); 
        }

        return $this->wrapResponse($newOrder);
    }


    private function getPromotionsInfo($rules){

        $promotionsInfo = [];
        if($rules){
            foreach($rules as $rule){
                $ruleId = $rule['rule_id'];
                $promotion_id = $rule['promotion_id'];
                // 获取最新的历史记录
                $history = PromotionsHistory::where('promotions_id', $promotion_id)->orderByDesc('id')->first();

                if(!$history){
                    $promotion = Promotions::with('rules')->find($promotion_id);
                    $history = PromotionsHistory::create([
                        'promotions_id' => $promotion_id,
                        'promotions_detail' => $promotion->toJson(JSON_UNESCAPED_UNICODE),
                    ]);
                }
                
                $promotionsInfo[] = [
                    'history_id' => $history->id,
                    'rule_id' => $ruleId,
                ];
            }
            
        }

        return $promotionsInfo ? json_encode($promotionsInfo, JSON_UNESCAPED_UNICODE) : '';
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy(CommonRequest $request, Order $order)
    {

        return $this->wrapResponse();
    }

    /**
     * 对取消订单进行归档
     */
    public function cancel_order_archive(ArchiveRequest $request)
    {

        $idArr = $request->input('ids');
        $retArr = [];
        foreach($idArr as $id){
            $item = OrderCancel::where('job_type', 1)->find($id);
            if($item){
                $item->update(['status' => 2]);
                $retArr[] = $id;
            }
        }
        return $this->wrapResponse($retArr);
    }

    /**
     * 对重复单设置为有效和无效
     */
    public function repeat_setting(RepeatRequest $request)
    {

        $idArr = $request->input('ids');
        $status = $request->input('revoke_distribute');

        $retArr = [];
        foreach($idArr as $id){
            $item = OrderRepeat::where('status', 0)->findOrFail($id);
            $item->update(['status' => $status]);
            $retArr[] = $id;
            if($status == OrderMapper::REPEAT_VALID) {
                $where = ['order_id' => $item->order_id];
                $update = ['repeat_flag' => 0];

                $audit = OrderAudit::firstWhere($where);
                if($audit) $audit->update($update);

                $distribute = OrderDistribute::firstWhere($where);
                if($distribute) $distribute->update($update);

                Order::firstWhere(['id' => $item->order_id])->update(['invalid_status' => 0]);
            }elseif($status == OrderMapper::REPEAT_INVALID) {
                $where = ['order_id' => $item->order_id];
                $update = ['repeat_flag' => 1];

                $audit = OrderAudit::firstWhere($where);
                if($audit) $audit->update($update);

                $distribute = OrderDistribute::firstWhere($where);
                if($distribute) $distribute->update($update);
                
                $order = Order::firstWhere(['id' => $item->order_id]);
                $order->update(['invalid_status' => 1]);

                
                $create = $order->toArray();
                $create['part'] = $create['pre_sale_id'] ? $create['pre_sale_id']%10 : 0;
                $create['order_sale_id'] = $create['pre_sale_id'];
                $create['invalid_type'] = 1;
                $create['job_type'] = CommonMapper::PRE_SALE;

                OrderInvalid::create($create);
            }
        }
        return $this->wrapResponse($retArr);
    }

    public function repeat_list(Request $request, $id)
    {
        $repeat = OrderRepeat::joinOrderDepartment(function($query){
            $query->whereIn('order_type',[1,2]);
        })->findOrFail($id);
        $orderId = $repeat->order_id;

        $order = Order::findOrFail($orderId);

        $query = OrderRepeat::joinOrderDepartment(function($query){
            $query->whereIn('order_type',[1,2]);
        })->where('repeat_id', $order->repeat_id);

        $with = [
            'order',
            'order.goods_info',
            'order.country',
            'order.language',
        ];


        $query->with($with);
        $query->filter($request->input());

        $ret = $query->dealList()->paginateFilter($request->input('limit'));
        $ret = OrderRelationResource::collection($ret)->resource;

        // 添加统计数据
        if(!$request->input('no_meta')){
            request()->merge(['meta' => $this->order_stats()]);
        }
        return $this->wrapResponse($ret);

    }

    /**
     * 主管点击开始分配订单
     */

    public function manager_start_distribute()
    {  
        $user = Auth('api')->user();
        if($user->level == CommonMapper::LEVEL_MANAGER && !$user->is_super){
            $type = 60;
            $ret = BaseDataMq::writeMq(compact('type'));

            if(!$ret) {
                throw new InvalidException('失败');
            }
        }else{
            throw new AuthorizationException('只有售前主管才有权限');
        }

        return $this->wrapResponse();
        
    }

    /**
     * 订单统计
     */
    public function order_stats()
    {
        $jobType =1;

        $order_stats = [
            'order_num_total' => RouteMapper::getModel('advertise', $jobType)->count(),       // 订单数, 通过下面4项之和：
            'audit_no' => RouteMapper::getModel('audit_not', $jobType)->count(),              // 未审核

            'audit_yes' => RouteMapper::getModel('audited', $jobType)->count(),              // 已审核

            'distribute_no' => RouteMapper::getModel('distribute_not', $jobType)->count(),          // 未分配

            'distribute_yes' => RouteMapper::getModel('distributed', $jobType)->count(),         // 已分配-
            'manul_order_num' => RouteMapper::getModel('manual', $jobType)->count(),        // 手动下单数
            'repeat_order_num' => RouteMapper::getModel('repeat', $jobType)->count(),       // 重复订单数
            'invalid_order_num' => RouteMapper::getModel('invalid', $jobType)->count(),      // 无效订单数
            'abnormal_order_num' => 0,     // 异常订单数，下面2项之和：
            'abnormal_no_dealwith' => RouteMapper::getModel('abnormal_no_dealwith', $jobType)->count(),      // 未处理异常订单数
            'abnormal_dealwith' => RouteMapper::getModel('abnormal_dealwith', $jobType)->count(),         // 已处理异常订单数

            'askforcancel_total' => RouteMapper::getModel('askforcancel',$jobType)->count(),
            'askforcancel_no_dealwith' => RouteMapper::getModel('askforcancel_no_dealwith',$jobType)->count(),// 取消订单申请 - 待处理

            'askforcancel_succ' => RouteMapper::getModel('askforcancel_cancel_succ', $jobType)->count(),       // 取消订单申请 - 取消成功

            'askforcancel_fail' => RouteMapper::getModel('askforcancel_cancel_fail', $jobType)->count(),       // 取消订单申请 - 取消失败
            'askforcancel_finish' => RouteMapper::getModel('askforcancel_place_on', $jobType)->count(),     // 取消订单申请 - 归档
        ];
        $order_stats['abnormal_order_num'] = $order_stats['abnormal_no_dealwith'] + $order_stats['abnormal_dealwith'];
        // $order_stats['askforcancel_total'] = $order_stats['askforcancel_no_dealwith'] + $order_stats['askforcancel_succ'] + $order_stats['askforcancel_fail'];

        $ret['order_stats'] = $order_stats;

        return $ret;

    }

}
