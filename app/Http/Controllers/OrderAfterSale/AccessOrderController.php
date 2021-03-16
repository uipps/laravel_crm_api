<?php
/**
 * OrderController
 * @author dev@xhat.com
 * @since 2020-03-10
 */
namespace App\Http\Controllers\OrderAfterSale;

use App\Exceptions\InvalidException;
use App\Http\Controllers\CommonController;
use App\Http\Requests\AfterSaleOrder\ArchiveRequest;
use App\Http\Requests\AfterSaleOrder\AuditRequest;
use App\Http\Requests\AfterSaleOrder\CommonRequest;
use App\Models\OrderPreSale\Order;
use App\Http\Requests\AfterSaleOrder\StoreRequest;
use App\Http\Requests\AfterSaleOrder\UpdateRequest;
use App\Http\Resources\OrderRelationResource;
use App\Mappers\CommonMapper;
use App\Mappers\OrderMapper;
use App\Mappers\RouteMapper;
use App\Models\Admin\Country;
use App\Models\OrderPreSale\OrderAbnormal;
use App\Models\OrderPreSale\OrderAttachment;
use App\Models\OrderPreSale\OrderCancel;
use App\Models\OrderPreSale\OrderDetail;
use App\Models\OrderPreSale\OrderManual;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class AccessOrderController extends CommonController
{
    public function __construct()
    {
        // 多表操作保证原子性，封装事务
        $this->middleware('transact')->only(['store', 'update', 'replenishAbleUpdate', 'abnormalRedeliveryAbleUpdate']);
    }

    public function index(CommonRequest $request)
    {
        /**
         * @var Builder|QueryBuilder $query
         */
        $query = $request->input('model'); //创建query对象

        if($query->getModel() instanceof Order){
            $with = [
                'goods_info',
                'country',
                'language',
                'after_sale',
            ];

        }else{
            $with = [
                'order',
                'order.goods_info',
                'order.country',
                'order.language',
                'order.after_sale',
            ];
        }


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

        if($detailArr){
            $order->goods_info()->createMany($detailArr);
        }

        $model = $query->create($request->input());

        return $this->wrapResponse($model);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show(CommonRequest $request, $id)
    {
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
                'after_sale',
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
                'order.after_sale',
                'order.attachment',
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
        $saveData = $request->input('save_data');
        $updateData = $request->input('update_data');
        $request->merge($saveData);
        $request->merge($updateData);

        $query = $request->input('model'); //创建query对象
        $model = $query->findOrFail($id);

        $routeOrderType = $request->input('routeOrderType');
        if($routeOrderType == 'redelivery_able'){
            $order = $model->order;
            $order->update([
                'order_second_type' => 3,
                'audit_status' => 0,
                'audit_time' => '',
            ]);
            $model->update([
                'type' => 3,
                'source_order_id' => $order->id,
                'source_order_no' => $order->order_no,
                'order_no' => $order->order_no.'_'.\createOrderNoSequenceNo($order->order_no),
                'order_id' => $order->id,
            ]);
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
                // 主管审核不能删除商品
                if(!in_array($routeOrderType, ['audit','audit_not','audited','reject'])){
                    $OrderDetail = $request->input('goods_info', []);

                    $model->order->goods_info()->delete();
        
                    foreach($OrderDetail as $detail){
                        $detail['order_id'] = $model->order_id;
                            OrderDetail::create($detail);
                        
                    }
                }
            }
            

            // foreach($OrderDetail as $detail){
            //     //存在order_id表示来自order_detail表
            //     $detail_id = Arr::get($detail,'order_id') ? Arr::get($detail,'id') : false;
            //     if($detail_id){
            //         OrderDetail::find($detail_id)->update($detail);
            //     }else{
            //         $detail['order_id'] = $model->order_id;
            //         OrderDetail::create($detail);
            //     }
                
            // }
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

        $query = RouteMapper::getModel('replenish_able', CommonMapper::AFTER_SALE);
        $model = $query->findOrFail($id);
        $order = $model->order;

        $OrderDetail = $request->input('goods_info', []);
        $newOrderArr = $order->toArray();
        $newOrderArr['order_second_type'] = 2; // 补发单
        $newOrderArr['order_type'] = OrderMapper::TYPE_AFTER_SALE; //售后手工


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

        if($OrderDetail){
            $newOrder->goods_info()->createMany($OrderDetail);
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

        $query = RouteMapper::getModel('abnormal_redelivery_able', CommonMapper::AFTER_SALE);
        $model = $query->findOrFail($id);
        $order = $model->order;

        $OrderDetail = $request->input('goods_info', []);
        $newOrderArr = $order->toArray();
        $newOrderArr['order_second_type'] = OrderMapper::SECOND_TYPE_ABNORMAL_REDELIVERY; // 异常重发单
        $newOrderArr['order_type'] = OrderMapper::TYPE_AFTER_SALE; //售后手工

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

        if($OrderDetail){
            $newOrder->goods_info()->createMany($OrderDetail);
        }

        return $this->wrapResponse($newOrder);
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
            $item = OrderCancel::where('job_type', 2)->find($id);
            if($item){
                $item->update(['status' => 2]);
                $retArr[] = $id;
            }
        }
        return $this->wrapResponse($retArr);
    }

    /**
     * 主管审核订单
     */
    public function charge_audit(AuditRequest $request)
    {
        $idArr = $request->input('ids');
        $status = $request->input('audit_status');

        $retArr = [];
        foreach($idArr as $id){
            $item = OrderManual::where('job_type', 2)->find($id);
            if($item){
                $item->update(['audit_status' => $status]);
                $item->order->update([
                    'audit_status' => $status,
                    'audit_time' => date('Y-m-d H:i:s')
                ]);
                $retArr[] = $id;
            }
        }
        return $this->wrapResponse($retArr);
    }

    /**
     * 订单统计
     */
    public function order_stats()
    {
        $jobType =2;

        $order_stats = [
            'manual' => RouteMapper::getModel('manual', $jobType)->count(),
            'audit' => RouteMapper::getModel('aftersale_audit', $jobType)->count(),
            'audit_not' => RouteMapper::getModel('aftersale_audit_not', $jobType)->count(),
            'audited' => RouteMapper::getModel('aftersale_audited', $jobType)->count(),
            'reject' => RouteMapper::getModel('aftersale_reject', $jobType)->count(),
            'abnormal' => RouteMapper::getModel('abnormal', $jobType)->count(),
            'abnormal_no_dealwith' => RouteMapper::getModel('abnormal_no_dealwith', $jobType)->count(),
            'abnormal_dealwith' => RouteMapper::getModel('abnormal_dealwith', $jobType)->count(),
            'askforcancel' => RouteMapper::getModel('askforcancel', $jobType)->count(),
            'askforcancel_no_dealwith' => RouteMapper::getModel('askforcancel_no_dealwith', $jobType)->count(),
            'askforcancel_cancel_succ' => RouteMapper::getModel('askforcancel_cancel_succ', $jobType)->count(),
            'askforcancel_cancel_fail' => RouteMapper::getModel('askforcancel_cancel_fail', $jobType)->count(),
        ];

        $ret['order_stats'] = $order_stats;

        return $ret;

    }

}
