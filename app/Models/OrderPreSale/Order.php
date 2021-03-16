<?php

namespace App\Models\OrderPreSale;

use App\Exceptions\InvalidException;
use App\Mappers\CommonMapper;
use App\Mappers\OrderMapper;
use App\Mappers\RouteMapper;
use App\ModelFilters\OrderFilter;
use App\Models\Admin\Country;
use App\Models\Admin\Department;
use App\Models\Admin\Language;
use App\Models\Admin\Promotions;
use App\Models\Admin\PromotionsGoodsNumRule;
use App\Models\Admin\User;
use App\Models\Traits\HasBase;
use App\Models\Traits\HasDepartment;
use Auth;
use Illuminate\Database\Eloquent\Builder;
use EloquentFilter\Filterable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

/**
 * @method static Order department
 */
class Order extends Model
{
    use HasBase;
    use HasDepartment;
    use Filterable;


    const ORDER_SOURCE_LIST = [4=>'crm', 15=>'网盟', 16=>'shopify', 17=>'分销']; // 订单来源,1网盟2分销3shopify => 15网盟,16shopify,17分销,4crm
    const ORDER_SOURCE_ALLOW = [4=>'crm', 15=>'网盟']; // 订单来源, 当前系统列表、增、删、统计只展示crm和网盟系统的数据，分销和shopify的数据会进入但不展示；
    const ORDER_TYPE_LIST   = [1=>'广告', 2=>'售前手工', 3=>'售后手工'];  // 订单类型 1广告2售前手工3售后手工
    const SMS_VERIFY_STATUS_LIST = [0=>'验证中', 1=>'成功', -1=>'失败']; // 短信验证状态
    const SHIPPING_STATUS_LIST = [8=>'配送中', 9=>'已签收', 16=>'已拒收', 30=>'未上线']; // 物流状态
    const AUDIT_STATUS_LIST = [0=>'待审核', 1=>'已审核', -1=>'已驳回']; // 审核状态

    const ORDER_TYPE_ADS    = 1;
    const ORDER_SECOND_TYPE_NORMAL = 1; //

    protected $table = 'order';
    public $timestamps = false;
    const CREATED_AT = 'created_time';
    const UPDATED_AT = 'updated_time';

    protected $appends = ['order_id', 'pre_opt_remark'];

    protected $fillable = [
        'month',                                // 分区字段 订单时间取yyyyMM(UTC+8)
        'order_source',                         // 订单来源,15网盟,16shopify,17分销,4crm
        'order_type',                           // 订单类型 1广告2售前手工3售后手工4手工取消
        'order_second_type',                    // 订单二级分类,1常规单2补发单3重发单
        'order_scope',                          // 订单范围 1内部单2外部单(售前和售后的手工订单都属于内部订单)
        'order_no',                             // 订单号
        'ship_no',                              // 运单号
        'repeat_id',                            // 重复单唯一标识id 对应重复订单编码id
        'customer_id',                          // 客户id
        'customer_name',                        // 客户名称
        'tel',                                  // 电话
        'real_tel',                             // 效验电话
        'email',                                // 邮箱
        'country_id',                           // 国家id
        'language_id',                          // 语言id
        'zone_prov_name',                       // 省/州
        'zone_city_name',                       // 城市
        'zone_area_name',                       // 区域
        'currency',                             // 货币单位
        'sale_amount',                          // 商品总金额
        'collect_amount',                       // 代收金额
        'received_amount',                      // 预付金额
        'discount_amount',                      // 优惠金额
        'premium_amount',                       // 溢价金额
        'order_amount',                         // 订单总金额
        'history_pre_sale_id',                  // 曾售前id 用户表id
        'pre_sale_id',                          // 售前id 用户表id
        'address',                              // 详细地址
        'zip_code',                             // 邮编
        'customer_remark',                      // 客户备注
        'sms_id',                               // 短信验证id
        'sms_verify_status',                    // 短信验证状态 0验证中-1成功-1失败
        'call_num',                             // 呼出次数
        'call_time',                            // 呼出时间 最近一次
        'call_duration',                        // 呼出时长 秒,最近一次
        'order_time',                           // 订单时间
        'order_long_time',                      // 订单时间戳
        'distribute_status',                    // 分配状态 0未分配1已分配
        'distribute_time',                      // 分配时间 最近一次
        'pre_opt_type',                         // 售前处理结果 对应订单处理类别id,最近一次
        'sale_remark',                          // 客服备注 最近一次
        'pre_opt_time',                         // 售前处理时间 最近一次
        'audit_status',                         // 审核状态 0未审核1已审核
        'audit_time',                           // 审核时间 最近一次
        'order_status',                         // 订单状态 对应订单状态表id
        'shipping_status',                      // 物流状态 对应订单状态表id
        'invalid_status',                       // 无效状态 0有效1系统判重2审核取消3审核重复
        'ship_wherehouse',                      // 发货仓库
        'ship_time',                            // 出库时间
        'ship_line',                            // 物流线路
        'creator_id',                           // 创建人
        'created_time',                         // 创建时间
        'updator_id',                           // 修改人
        'updated_time',                         // 更新时间
        'department_id',                        // 部门id
        'last_msg_time',                        // 订单消息时间
        'deleted_time',                         // 删除时间
    ];

    public function getOrderTypeNameAttribute()
    {
        return Arr::get(self::ORDER_TYPE_LIST, $this->order_type, '');
    }

    public function getOrderIdAttribute()
    {
        return $this->id;
    }

    public function getPreOptRemarkAttribute()
    {
        return $this->sale_remark;
    }

    public function getCallTimeAttribute($value)
    {

        return $value == '0000-01-01 00:00:00' ? '' : $value;
    }

    public function getDistributeTimeAttribute($value)
    {
        return $value == '0000-01-01 00:00:00' ? '' : $value;
    }

    public function getAuditTimeAttribute($value)
    {
        return $value == '0000-01-01 00:00:00' ? '' : $value;
    }

    public function getShipTimeAttribute($value)
    {
        return $value == '0000-01-01 00:00:00' ? '' : $value;
    }

    public function getPreOptTimeAttribute($value)
    {
        return $value == '0000-01-01 00:00:00' ? '' : $value;
    }

    public function setCallTimeAttribute($value)
    {
        if(!$value){
            $value = '0000-01-01 00:00:00';
        }

        $this->attributes['call_time'] = $value;
    }

    public function setDistributeTimeAttribute($value)
    {
        if(!$value){
            $value = '0000-01-01 00:00:00';
        }

        $this->attributes['distribute_time'] = $value;
    }

    public function setAuditTimeAttribute($value)
    {
        if(!$value){
            $value = '0000-01-01 00:00:00';
        }

        $this->attributes['audit_time'] = $value;
    }

    public function setShipTimeAttribute($value)
    {
        if(!$value){
            $value = '0000-01-01 00:00:00';
        }

        $this->attributes['ship_time'] = $value;
    }

    public function setPreOptTimeAttribute($value)
    {
        if(!$value){
            $value = '0000-01-01 00:00:00';
        }

        $this->attributes['pre_opt_time'] = $value;
    }

    public function setSaleRemarkAttribute($value)
    {
        $this->attributes['sale_remark'] = $value ?: '';
    }

    public function attachment()
    {
        return $this->hasOne(OrderAttachment::class);
    }


    /**
     * https://github.com/Tucker-Eric/EloquentFilter
     *
     * 页面条件查询使用
     */
    public function modelFilter()
    {
        return $this->provideFilter(OrderFilter::class);
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * 关联country
     */
    public function country()
    {
        return $this->belongsTo(Country::class);
    }

    /**
     * 关联sys_language
     */
    public function language()
    {
        return $this->belongsTo(Language::class);
    }

    /**
     * 关联order_detail
     */
    public function order_detail()
    {
        return $this->hasMany(OrderDetail::class);
    }

    /**
     * 关联order_detail
     */
    public function goods_info()
    {
        return $this->hasMany(OrderDetail::class);
    }

    /**
     * 售前客服
     */
    public function pre_sale()
    {
        return $this->belongsTo(User::class, 'pre_sale_id');
    }

    /**
     * 售前客服最后一次处理结果
     */
    public function pre_opt()
    {
        return $this->belongsTo(OrderOptType::class, 'pre_opt_type');
    }

    /**
     * 售后客服
     */
    public function after_sale()
    {
        // return $this->belongsToMany(User::class, OrderManual::class, 'order_id','order_sale_id');

        return $this->belongsTo(User::class, 'creator_id');
    }

    /**
     * 关联 order_audit
     */
    public function order_audit()
    {
        return $this->hasOne(OrderAudit::class);
    }

    /**
     * 关联 order_distribute
     */
    public function order_distribute()
    {
        return $this->hasOne(OrderDistribute::class);
    }

    /**
     * 关联 order_invalid
     */
    public function order_invalid()
    {
        return $this->hasOne(OrderInvalid::class);
    }

    public function manual_order_child()
    {
        return $this->belongsToMany(Order::class, 'order_manual', 'source_order_id', 'order_id');
        // return $this->hasMany(OrderManual::class, 'order_id', 'source_order_id');
    }

    public function ab_redelivery_child(){
        
        return $this->hasMany(OrderManual::class, 'source_order_id', 'id')->where('type', 5);
    }


    /**
     * 动态注册事件
     */
    public static function boot(){
        parent::boot();

        // 创建中订单触发的事件
        static::creating(function (Order $model) {
            $user = Auth('api')->user();
            $jobType = request()->input('request_job_type');

            $model->month = date('Ym');
            $model->order_no = generateOrderSn();
            $model->order_time = date('Y-m-d H:i:s');
            $model->order_long_time = time();
            $model->creator_id = Auth('api')->id();
            $model->department_id = Auth('api')->user()->department_id;
            $model->currency = Arr::get(Country::find($model->country_id)->currency, 'code');
            $model->order_status = 3; //待处理
            $model->shipping_status = 30;
            $model->pre_opt_type = request('pre_opt_type', 0);
            $model->order_source = 4;
            $model->sale_remark = request('sale_remark', '');
            $model->audit_status = request('audit_status', 0);
            $model->audit_time = request('audit_time', '');
            

            // 售前主管
            // if($jobType == CommonMapper::PRE_SALE && $user->level == CommonMapper::LEVEL_MANAGER){
            //     $model->audit_status = 1;
            //     $model->audit_time = date('Y-m-d H:i:s');
            // }else{
                
            // }
        });

        static::saving(function (Order $model) {
            if($model->isDirty('pre_opt_type') && $model->pre_opt_type && request('submit_type') == OrderMapper::SUBMIT_TYPE_DONE){
                $model->pre_opt_time = date('Y-m-d H:i:s');
            }

            if(request('goods_info')){
                // sale_amount 商品总金额，通过商品价格*num计算得出；
                $sale_amount = 0;
                $sale_remain = 0;
                foreach (request('goods_info') as $goods_info) {
                    $goodsAmount = bcmul($goods_info['unit_price'], $goods_info['num']);
                    $sale_amount = bcadd($sale_amount, $goodsAmount , 2);

                    $rules = Arr::get($goods_info, 'rules', []);
                    if($rules){
                        $remainAmount = $goodsAmount;

                        foreach($rules as $rule){
                            // 逐个规则校验： 最少商品数、折扣、是否可叠加、规则范围、商品范围，任何一项有变化，则认为活动有变更
                            $ruleId = $rule['rule_id'];
                            $ruleModel =PromotionsGoodsNumRule::findOrFail($ruleId);
                            $promotion = $ruleModel->promotion;
                            if(
                                $ruleModel->min_num != $rule['min_num'] ||
                                $ruleModel->discount != $rule['discount'] ||
                                $promotion->rule_attr != $rule['rule_attr'] ||
                                $promotion->rule_scope != $rule['rule_scope'] ||
                                $promotion->goods_scope != $rule['goods_scope']
                            ){
                                throw new InvalidException('promotion has change');
                            }

                            // 校验规则正确性

                            // 计算优惠金额
                            $remainAmount = bcmul($remainAmount, $ruleModel->discount);
                        }

                        $sale_remain = bcadd($sale_remain, $remainAmount);
                    }

                }

                if(request()->has('discount_amount')){
                    $discountAmount = request('discount_amount', 0);
                }else{
                    $sale_remain = $sale_remain ?: $sale_amount;
                    $discountAmount = bcsub($sale_amount, $sale_remain);
                }

                $model->discount_amount = $discountAmount;
                $model->sale_amount = $sale_amount;
                $model->premium_amount = request('premium_amount', 0);
                $model->received_amount = request('received_amount', 0);

                $orderAmount = bcadd($sale_amount, $model->premium_amount, 2);
                $orderAmount = bcsub($orderAmount, $model->discount_amount, 2);
                $collectAmount = bcsub($orderAmount, $model->received_amount, 2);

                if(in_array(request('routeOrderType'),['replenish_able'])){
                    $model->collect_amount = 0;
                    $model->order_amount = 0;
                }else{
                    $model->collect_amount = $collectAmount;
                    $model->order_amount = $orderAmount;
                }

            }



        });

        // 创建，操作记录
        static::created(function (Order $model) {
            $jobType = request()->input('request_job_type');
            $submitType =request()->input('submit_type');
            $preOptType = request()->input('pre_opt_type');

            $insert = [
                'order_id' => $model->id,
                'order_status' => $model->order_status,
                'month' => date("Ym"),
                'optator_id' => Auth('api')->id(),
                'remark' => '',
            ];

            $user = Auth('api')->user();
            // 售前主管
            if($jobType == CommonMapper::PRE_SALE && $user->level == CommonMapper::LEVEL_MANAGER){

                if($submitType == CommonMapper::SUBMIT_SAVING)
                {
                    if($model->order_second_type == OrderMapper::SECOND_TYPE_GENERAL)
                    {
                        $insert['opt_type_id'] = 2;
                    }elseif($model->order_second_type == OrderMapper::SECOND_TYPE_REPLENISH)
                    {
                        $insert['opt_type_id'] = 33;
                    }elseif($model->order_second_type == OrderMapper::SECOND_TYPE_REDELIVERY)
                    {
                        $insert['opt_type_id'] = 38;
                    }

                    OrderOptRecord::create($insert);
                    
                }
                
            }
            // 售前客服
            if($jobType == CommonMapper::PRE_SALE && $user->level == CommonMapper::LEVEL_SERVICE){

            }
            // 售后主管
            if($jobType == CommonMapper::AFTER_SALE && $user->level == CommonMapper::LEVEL_MANAGER ){
                
            }
            // 售后客服
            if($jobType == CommonMapper::AFTER_SALE && $user->level == CommonMapper::LEVEL_SERVICE){
                if($submitType == CommonMapper::SUBMIT_SAVING)
                {
                    if($model->order_second_type == OrderMapper::SECOND_TYPE_GENERAL)
                    {
                        $insert['opt_type_id'] = 62;
                    }elseif($model->order_second_type == OrderMapper::SECOND_TYPE_REPLENISH)
                    {
                        $insert['opt_type_id'] = 78;
                    }elseif($model->order_second_type == OrderMapper::SECOND_TYPE_REDELIVERY)
                    {
                        $insert['opt_type_id'] = 83;
                    }elseif($model->order_second_type == OrderMapper::SECOND_TYPE_CLUE)
                    {
                        $insert['opt_type_id'] = 87;
                    }

                    OrderOptRecord::create($insert);
                }
                
            }

        });

        // 更新，操作记录
        static::updated(function (Order $model) {


        });

        static::saved(function (Order $model) {
            $jobType = request()->input('request_job_type');
            $submitType =request()->input('submit_type');
            $preOptType = request()->input('pre_opt_type');

            $insert = [
                'order_id' => $model->id,
                'order_status' => $model->order_status,
                'month' => date("Ym"),
                'optator_id' => Auth('api')->id(),
                'remark' => '',
            ];

            $user = Auth('api')->user();
            // 售前主管
            if($jobType == CommonMapper::PRE_SALE && $user->level == CommonMapper::LEVEL_MANAGER){
                $routeName = request()->route()->getName();
                if($model->isDirty('distribute_status') && strpos($routeName, 'distribute_order')){
                    $service = User::findOrFail($model->pre_sale_id);
                    if($model->distribute_status == 1)
                    {
                        $insert['opt_type_id'] = 3;
                        $insert['remark'] = "主管: {$user->real_name}分配给客服: {$service->real_name}";

                        OrderOptRecord::create($insert);
                    }elseif($model->distribute_status == 0)
                    {
                        $insert['opt_type_id'] = 4;
                        $insert['remark'] = "主管: {$user->real_name}撤销分配给客服: {$service->real_name}";

                        OrderOptRecord::create($insert);
                    }

                    return false;

                }

                if($submitType == CommonMapper::SUBMIT_OK)
                {
                    if($model->order_second_type == OrderMapper::SECOND_TYPE_GENERAL)
                    {
                        $insert['opt_type_id'] = 1;
                    }elseif($model->order_second_type == OrderMapper::SECOND_TYPE_REPLENISH)
                    {
                        $insert['opt_type_id'] = 31;
                    }elseif($model->order_second_type == OrderMapper::SECOND_TYPE_REDELIVERY)
                    {
                        $insert['opt_type_id'] = 36;
                    }
                    OrderOptRecord::create($insert);
                }
                
            }
            // 售前客服
            if($jobType == CommonMapper::PRE_SALE && $user->level == CommonMapper::LEVEL_SERVICE){
                if($submitType == CommonMapper::SUBMIT_OK){
                    // 员工审单
                    if($preOptType){
                        $insert['opt_type_id'] = $preOptType;
                    }

                    OrderOptRecord::create($insert);
                }

            }

            // 售后主管
            if($jobType == CommonMapper::AFTER_SALE && $user->level == CommonMapper::LEVEL_MANAGER ){
                if($model->isDirty('audit_status')){
                    
                    if($model->audit_status == 1)
                    {
                        $insert['opt_type_id'] = 63;
                    }
                    elseif($model->audit_status == -1)
                    {
                        $insert['opt_type_id'] = 64;
                    }
                    OrderOptRecord::create($insert);

                }
            }
            // 售后客服
            if($jobType == CommonMapper::AFTER_SALE && $user->level == CommonMapper::LEVEL_SERVICE){
                if($submitType == CommonMapper::SUBMIT_OK)
                {
                    if($model->order_second_type == OrderMapper::SECOND_TYPE_GENERAL)
                    {
                        $insert['opt_type_id'] = 61;
                    }elseif($model->order_second_type == OrderMapper::SECOND_TYPE_REPLENISH)
                    {
                        $insert['opt_type_id'] = 76;
                    }elseif($model->order_second_type == OrderMapper::SECOND_TYPE_REDELIVERY)
                    {
                        $insert['opt_type_id'] = 81;
                    }elseif($model->order_second_type == OrderMapper::SECOND_TYPE_CLUE)
                    {
                        $insert['opt_type_id'] = 86;
                    }

                    OrderOptRecord::create($insert);
                }

                
            }

        });
    }


    public function scopeJoinManualDepartment($query, $callback = null) {

        $existsQuery = OrderManual::aftersalePemission()->whereRaw('order_manual.order_id = order.id');
        if (!is_null($callback)){
            $callback($existsQuery);
        }

        $query->addWhereExistsQuery($existsQuery->getQuery());        

        return $query;
    }


}
