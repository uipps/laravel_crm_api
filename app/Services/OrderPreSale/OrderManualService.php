<?php

namespace App\Services\OrderPreSale;

use App\Dto\OrderDetailDto;
use App\Dto\OrderDto;
use App\Dto\OrderStatsPreSaleDto;
use App\Libs\Utils\ErrorMsg;
use App\Dto\DataListDto;
use App\Dto\ResponseDto;
use App\Dto\OrderManualDto;
use App\Models\Admin\User;
use App\Models\OrderPreSale\OrderManual;
use App\Repositories\Admin\CountryRepository;
use App\Repositories\Admin\CurrencyRepository;
use App\Repositories\Customer\CustomerAddressRepository;
use App\Repositories\Customer\CustomerRepository;
use App\Repositories\OrderPreSale\OrderDetailRepository;
use App\Repositories\OrderPreSale\OrderManualRepository;
use App\Repositories\Admin\UserRepository;
use App\Repositories\OrderPreSale\OrderOptRecordRepository;
use App\Repositories\OrderPreSale\OrderRepository;
use App\Services\BaseService;
use Illuminate\Support\Facades\Validator;


class OrderManualService extends BaseService
{
    protected $theRepository;
    protected $departmentRepository;
    protected $orderRepository;
    protected $orderDetailRepository; // 就是商品信息
    protected $customer_repo;
    protected $customer_address_repo; // 客户地址信息

    public function __construct() {
        $this->theRepository = new OrderManualRepository();
        $this->orderRepository = new OrderRepository();
        $this->orderDetailRepository = new OrderDetailRepository();
        $this->userRepository = new UserRepository(); // 用于权限检查
    }

    public function getList($request=null) {
        if (!$request) $request = request()->all(); // 参数接收
        $responseDto = new ResponseDto();

        // 参数校验数组
        $rules = [
            'page' => 'sometimes|integer',
            'limit' => 'sometimes|integer',
        ];
        $validate = Validator::make($request, $rules);
        if ($validate->fails()) {
            $error_list = $validate->errors()->all();
            $responseDto->status = ErrorMsg::PARAM_ERROR;
            $responseDto->msg = implode("\r\n", $error_list);
            return $responseDto;
        }

        // 主管能看到本部门的全部列表；员工只能看到自己的
        $login_user_info = self::getCurrentLoginUserInfo();
        // 如果是管理员，能看到所有
        $child_dept_list = [];
        if (1 != $login_user_info['role_id']) {
            if (User::LEVEL_ADMIN == $login_user_info['level']) {
                // 主管能看到本部门的全部列表
                $child_dept_list = parent::getChildrenDepartmentByDeptId($login_user_info['department_id']);
                if (!isset($request['department_id'])) $request['department_id'] = ['in', $child_dept_list];
            } else if (User::LEVEL_STAFF == $login_user_info['level']) {
                // 员工只能看到自己的
                if (!isset($request['order_sale_id'])) $request['order_sale_id'] = $login_user_info['id'];
            }
        }
        $request['job_type'] = 1; // order_manual 表 岗位类别 1售前2售后

        // 获取数据，包含总数字段
        $list = $this->theRepository->getList($request);
        if (!$list || !isset($list[$responseDto::DTO_FIELD_TOTOAL]) || !isset($list[$responseDto::DTO_FIELD_LIST])) {
            ErrorMsg::FillResponseAndLog($responseDto, ErrorMsg::DATA_EMPTY);
            return $responseDto;
        }
        if ($list[$responseDto::DTO_FIELD_LIST]) {
            // 所有的 order_id 列表
            $order_ids = [];
            foreach ($list[$responseDto::DTO_FIELD_LIST] as $v_detail) {
                $order_ids[] = $v_detail['order_id'];
            }
            // 通过 order_id，获取对应的订单详情数据
            $order_info_list = $this->orderRepository->getOrderInfosByOrderIds($order_ids); // 批量获取
            // 通过 order_id，获取订单对应的商品详情数据
            $goods_info_list = $this->orderDetailRepository->getGoodsListByOrderIds($order_ids); // 三维数组

            // 成功，返回列表信息
            foreach ($list[$responseDto::DTO_FIELD_LIST] as $key => $v_detail) {
                $list[$responseDto::DTO_FIELD_LIST][$key] = self::getOneOrderInfo($v_detail, $order_info_list, $goods_info_list);
            }
        }
        $data_list = new DataListDto();
        if (!isset($request[$responseDto::WITHOUT_ORDER_STATS]) || !$request[$responseDto::WITHOUT_ORDER_STATS]) {
            // 默认情况，所有订单列表都要带上订单统计数据；如果设置不需要携带统计数据则跳过
            $redis_stats_data = $this->orderRepository->getOrderNumStatsByUserId($login_user_info['id'], $login_user_info, $child_dept_list);
            $order_stat = new OrderStatsPreSaleDto();
            $order_stat->Assign($redis_stats_data); // 从redis缓存获取数据
            $data_list->meta[$responseDto::DTO_FIELD_ORDER_STATS] = $order_stat;
        }
        $data_list->Assign($list);
        $responseDto->data = $data_list;

        return $responseDto;
    }

    private function getOneOrderInfo($v_detail, $order_info_list, $goods_info_list) {
        $order_id = $v_detail['order_id'];
        // 字段映射，数据表中只有status字段，无status_manual字段，但是前端需要status_manual字段；DTO修改没有作用，因为进行数组合并
        if (isset($v_detail['status'])) {
            $v_detail['status_manual'] = $v_detail['status'];
            if (0 == $v_detail['status']) {
                $v_detail['submit_type'] = 2;
            }
        }

        // DTO合并方法一，验证可行
        $v_info = array_merge((array)(new OrderManualDto()), (array)(new OrderDto()));
        if (isset($v_info['order_sale_id'])) {
            // 售前客服字段订单表中有
            unset($v_info['order_sale_id']);
            // $v_info['job_type'] = 1; // 售前
        }
        $v_info = \array_assign($v_info, $v_detail); // 赋值

        // 订单详情赋值
        if (isset($order_info_list[$order_id]))
            $v_info = \array_assign($v_info, $this->addAllAttrName2Data($order_info_list[$order_id]));

        // 商品详情赋值
        if (isset($goods_info_list[$order_id])) {
            foreach ($goods_info_list[$order_id] as $goods_val) {
                $goods_dto = new OrderDetailDto();
                $goods_dto->Assign($goods_val);
                $v_info['goods_info'][] = $goods_dto; // 可能有多条商品信息，放到字段 goods_info 上
            }
        }
        return $v_info;
    }

    private function getSourceOrderInfo($v_detail, $goods_info_list, $order_opt_records=[]) {
        $order_id = $v_detail['id'];
        // 字段映射，数据表中无 submit_type 字段，但是前端需要submit_type字段；DTO修改没有作用，因为进行数组合并
        if (0 == $v_detail['audit_status'] || 41 == $v_detail['pre_opt_type']) {
            $v_detail['submit_type'] = 2;
        }

        $v_info = new OrderDto();
        $v_info->Assign($this->addAllAttrName2Data($v_detail));

        // 商品详情赋值
        if (isset($goods_info_list[$order_id])) {
            foreach ($goods_info_list[$order_id] as $goods_val) {
                $goods_dto = new OrderDetailDto();
                $goods_dto->Assign($goods_val);
                $v_info->goods_info[] = $goods_dto; // 可能有多条商品信息，放到字段 goods_info 上
            }
        }
        // 订单操作记录赋值
        /*if ($order_opt_records) {
            foreach ($order_opt_records as $l_val) {
                $opt_record = new OrderOptRecordDto();
                $opt_record->Assign($this->addOptTypeName($l_val));
                $v_info->order_opt_records[] = $opt_record; // 可能有多条
            }
        }*/
        return $v_info;
    }

    public function addOrUpdate($request = null) {
        if (!$request) $request = request()->all();
        $responseDto = new ResponseDto();

        $login_user_info = self::getCurrentLoginUserInfo();
        if (User::LEVEL_ADMIN != $login_user_info['level']) {
            // 只有主管才可以创建手工单
            ErrorMsg::FillResponseAndLog($responseDto, ErrorMsg::ORDER_MANUAL_CREATE_BY_ADMIN);
            return $responseDto;
        }

        if ('cli' != php_sapi_name()) $current_uid = auth('api')->id();
        else $current_uid = ($request['creator_id'] ?? 0) + 0;
        if (isset($request['id']) && isset($request['order_id'])) {
            ErrorMsg::FillResponseAndLog($responseDto, ErrorMsg::PARAM_ERROR);
            return $responseDto;
        }
        if (isset($request['id'])) {
            $request['order_id'] = isset($request['id']);
            unset($request['id']);
        }
        // 参数校验数组, 当前登录用户是否有权限暂不验证，后面统一处理
        $rules = [
            'submit_type' => 'required|integer|min:1', // 提交类型，1-提交 2-保存；
            'order_id' => 'sometimes|integer|min:1',
        ];
        if (1 == $request['submit_type']) {
            $rules['pre_sale_id'] = 'required|integer|min:1'; // 提交必须指定售前客服
            $rules['language_id'] = 'required|integer|min:1';
            $rules['country_id'] = 'required|integer|min:1'; // 国家id
            $rules['zone_prov_name'] = 'required|string';
            $rules['zone_city_name'] = 'required|string';
            $rules['zone_area_name'] = 'required|string';
            $rules['goods_info'] = 'required';
            $rules['customer_name'] = 'required|string';
            $rules['tel'] = 'required';
        }

        $validate = Validator::make($request, $rules);
        if ($validate->fails()) {
            $error_list = $validate->errors()->all();
            $responseDto->status = ErrorMsg::PARAM_ERROR;
            $responseDto->msg = implode("\r\n", $error_list);
            return $responseDto;
        }

        if (!isset($request['collect_amount'])) $request['collect_amount'] = 0;     // 代收金额
        if (!isset($request['received_amount'])) $request['received_amount'] = 0;   // 预付金额
        if (!isset($request['discount_amount'])) $request['discount_amount'] = 0;   // 优惠金额
        if (!isset($request['premium_amount'])) $request['premium_amount'] = 0;     // 溢价金额
        if (!isset($request['pre_sale_id'])) $request['pre_sale_id'] = 0;           // 售前客服ID

        // 依据不同的提交类型，修改状态值
        if (1 == $request['submit_type']) {
            // 提交, status=1 ，需要产生客户信息，还有客户地址信息
            $request['status'] = 1;
        } else if (2 == $request['submit_type']) {
            // 保存, status=0，未提交
            $request['status'] = 0;
        } else if (3 == $request['submit_type']) {
            // 取消订单 status=-1
            //$request['status'] = -1;
            return $this->delete($request['order_id']); // 直接取消订单
        } else {
            ErrorMsg::FillResponseAndLog($responseDto, ErrorMsg::UNSUPPORTED_TYPE);
            return $responseDto;
        }

        $curr_datetime = date('Y-m-d H:i:s');
        $data_arr = $request; // 全部作为
        if (isset($request['order_id']) && $request['order_id']) {
            $data_arr['updator_id'] = $current_uid;
        } else {
            $data_arr['creator_id'] = $current_uid;
            $data_arr['updator_id'] = $data_arr['creator_id'];
            $data_arr['created_time'] = $curr_datetime;
        }
        // 数据增加几个默认值
        $data_arr['updated_time'] = $curr_datetime;

        if (isset($request['order_id']) && $request['order_id']) {
            // 修改保存状态的订单，只能修改保存状态的，保存状态可以无限次被保存，提交状态不能再修改
            // 检查该记录是否存在
            $v_detail = $this->theRepository->getInfoById($request['order_id']);
            if (!$v_detail || !isset($v_detail['status'])) {
                ErrorMsg::FillResponseAndLog($responseDto, ErrorMsg::DATA_NOT_EXISTS);
                return $responseDto;
            }
            $v_order_detail = $this->orderRepository->getInfoById($request['order_id']);

            // 检查订单状态是否为保存 0-未提交 1-已提交 -1已取消
            if (0 != $v_detail['status']) {
                // 只能修改未提交状态的手工单
                ErrorMsg::FillResponseAndLog($responseDto, ErrorMsg::ORDER_STATUS_ERROR);
                return $responseDto;
            }

            // 更新数据，分为提交和继续保存
            $rlt = $this->updateOrderAndManualData($data_arr, $request, $v_order_detail, $v_detail, $curr_datetime, $current_uid);
            if (!$rlt) {
                ErrorMsg::FillResponseAndLog($responseDto, ErrorMsg::UPDATE_DB_FAILED);
                return $responseDto;
            }
        } else {
            // 提交或者保存
            if (1 == $request['submit_type']) {
                return $this->submitManualOrder($request, $data_arr, $curr_datetime, $current_uid);
            } else {
                return $this->insertSaveManualOrder($request, $data_arr, $curr_datetime, $current_uid);
            }
        }

        return $responseDto;
    }

    // 计算一下相关数值
    public function formatForUpdateSubmitManualOrder($data_arr, $request, $db_order_detail, $db_manual_order_detail=[]) {
        // TODO 下单时间用哪个?
        $curr_datetime = date('Y-m-d H:i:s');
        //if (isset($db_order_detail['order_time']) && strtotime($db_order_detail['order_time']) > 0)
            //$curr_datetime = $db_order_detail['order_time'];
        $login_user_info = self::getCurrentLoginUserInfo();
        $current_uid = auth('api')->id();

        if (!isset($request['collect_amount'])) $request['collect_amount'] = 0;     // 代收金额
        if (!isset($request['received_amount'])) $request['received_amount'] = 0;   // 预付金额
        if (!isset($request['discount_amount'])) $request['discount_amount'] = 0;   // 优惠金额
        if (!isset($request['premium_amount'])) $request['premium_amount'] = 0;     // 溢价金额
        if (!isset($request['pre_sale_id'])) $request['pre_sale_id'] = 0;           // 售前客服ID

        // 添加手工订单、补发订单、重发订单, 线索单(售后，售前没有线索单)
        if (!isset($request['type'])) $request['type'] = OrderManual::TYPE_NORMAL; // 默认常规单
        $data_arr = array_merge($request, $data_arr);

        // 客户地址是否存在
        if (!$this->customer_address_repo) $this->customer_address_repo = new CustomerAddressRepository();
        // 这里先查地址库，再查客户表，因为手机号可能已经存在于地址库，是某用户的多个手机号中的一个，因此采用反向查找方式
        if (!isset($data_arr['country_id']))
            $customer_address_info = $this->customer_address_repo->getInfoByCountryIdAndTel($db_order_detail['country_id'], $db_order_detail['tel']);
        else $customer_address_info = $this->customer_address_repo->getInfoByCountryIdAndTel($data_arr['country_id'], $data_arr['tel']);

        // 准备插入order表字段拼装
        $data_arr['month'] =  $db_order_detail['month'] ?? date('Ym'); // 分区
        $data_arr['order_source'] = 4;  // TODO 来源CRM ???? 增加一个可选值？
        $data_arr['order_type'] = 2;    // 售前手动
        $data_arr['order_second_type'] = $request['type']; // 补发，重发，常规单
        $data_arr['order_scope'] = 1;   // 订单范围 1内部单2外部单(售前和售后的手工订单都属于内部订单)
        if ($db_order_detail && isset($db_order_detail['order_no']) && $db_order_detail['order_no']) {
            $data_arr['order_no'] = $db_order_detail['order_no'];
        } else $data_arr['order_no'] = \generateOrderSn(); // 订单号，保存的时候，也生产订单号吧

        $data_arr['repeat_id'] = 0;  // int，如何找出来 TODO 计算规则????
        $data_arr['customer_id'] = $customer_address_info['customer_id'] ?? 0;  // 客户ID
        //$data_arr['customer_name'] = $request['customer_name'];  // 客户名称就是订单中的名称，提交过来的名称
        //$data_arr['tel'] = $request['tel'];   // 前面已经赋值，这里不再重新赋值，包括国家ID，省市区，语言等
        // @2020.4.24 钉钉群里面确认就是按照国家的货币
        $data_arr['currency'] = $db_order_detail['currency'] ?? '';
        if (isset($data_arr['country_id']) && $data_arr['country_id']) {
            // 保存的时候，可能还没有选国家
            $country_info = (new CountryRepository())->getInfoById($data_arr['country_id']);
            if ($country_info && isset($country_info['code'])) {
                // 通过三字码找对应的货币
                $currency_info = (new CurrencyRepository())->getInfoByCode3($country_info['code']);
                if ($currency_info && isset($currency_info['code']))
                    $data_arr['currency'] = $currency_info['code'];
            }
        }

        // sale_amount 商品总金额，通过商品价格*num计算得出；
        $sale_amount = 0;
        if (isset($request['goods_info']) && $request['goods_info']) {
            foreach ($request['goods_info'] as $goods_info) {
                $sale_amount = bcadd($sale_amount, bcmul($goods_info['unit_price'], $goods_info['num']));
            }
        }
        $request['sale_amount'] = $sale_amount;
        $data_arr['sale_amount'] = $request['sale_amount'];  // 商品总金额
        // 其他金额在上面已经赋初值
        // 订单总金额为 = 商品总金额 + 溢价金额 - 优惠金额 - 预付金额
        $request['order_amount'] = $request['sale_amount'] + $request['premium_amount'] - $request['discount_amount'] - $request['received_amount'];
        $data_arr['order_amount'] = $request['order_amount'];
        $data_arr['order_time'] = $curr_datetime;
        $data_arr['order_long_time'] = strtotime($curr_datetime); //bcmul(strtotime($curr_datetime), 1000000, 0);
        $data_arr['distribute_status'] = 1; // 分配状态 0未分配1已分配，手工下单提交，直接分配给指定的客服
        $data_arr['distribute_time'] = $curr_datetime;
        $data_arr['audit_status'] = 1; // 审核状态 0未审核1已审核，手工下单直接审核通过
        $data_arr['audit_time'] = $curr_datetime;
        $data_arr['order_status'] = 22; // 已经审核
        $data_arr['shipping_status'] = 0; // 0没有物流信息 物流状态未上线 30 TODO
        $data_arr['invalid_status'] = 0; // 无效状态 0有效1系统判重2审核取消3审核重复
        $data_arr['department_id'] = $login_user_info['department_id'];
        // 超级管理员没有部门，则暂时用售前客服的部门id，上线后应该不会出现这个情况
        if (0 == $login_user_info['department_id']) {
            $pre_sale_info = (new UserRepository())->getUserById($request['pre_sale_id']);
            $data_arr['department_id'] = $pre_sale_info['department_id'];
        }

        // order_detail表中的字段暂时没有拼装，全是商品信息

        // order_manual表中的字段
        $data_arr['order_sale_id'] = $request['pre_sale_id'];  // 售前客服ID
        $data_arr['part'] = $data_arr['order_sale_id'] % 10;  //
        // department_id
        // order_id
        // order_no
        // type                             // 类别 1常规单2补发单3重发单4线索
        $data_arr['job_type'] = 1;          // 岗位类型 1售前2售后
        $data_arr['source_order_id'] = 0;   // 原订单id
        $data_arr['source_order_no'] = '';
        $data_arr['remark'] = $request['remark'] ?? '';
        $data_arr['status'] = 1;            //状态 0未提交1已提交-1已取消
        // opt_time

        return $data_arr;
    }

    // 提交手工单
    private function submitManualOrder($request, $data_arr, $curr_datetime, $current_uid) {
        $responseDto = new ResponseDto();

        $login_user_info = self::getCurrentLoginUserInfo();

        // 参数校验数组, 当前登录用户是否有权限暂不验证，后面统一处理
        $rules = [
            'status' => 'required|integer',       // 提交类型，1-提交 0-未提交；
            'order_id' => 'sometimes|integer|min:1',
        ];
        // 提交的时候，这些参数必须提供
        $rules['pre_sale_id'] = 'required|integer|min:1'; // 提交必须指定售前客服
        $rules['language_id'] = 'required|integer|min:1';
        $rules['country_id'] = 'required|integer|min:1'; // 国家id
        $rules['zone_prov_name'] = 'required|string';
        $rules['zone_city_name'] = 'required|string';
        $rules['zone_area_name'] = 'required|string';
        $rules['goods_info'] = 'required';
        $rules['customer_name'] = 'required|string';
        $rules['tel'] = 'required';

        $validate = Validator::make($request, $rules);
        if ($validate->fails()) {
            $error_list = $validate->errors()->all();
            $responseDto->status = ErrorMsg::PARAM_ERROR;
            $responseDto->msg = implode("\r\n", $error_list);
            return $responseDto;
        }
        if (1 != $request['status']) {
            ErrorMsg::FillResponseAndLog($responseDto, ErrorMsg::ORDER_SUBMIT_TYPE_ERROR);
            return $responseDto;
        }

        if (!isset($request['collect_amount'])) $request['collect_amount'] = 0;     // 代收金额
        if (!isset($request['received_amount'])) $request['received_amount'] = 0;   // 预付金额
        if (!isset($request['discount_amount'])) $request['discount_amount'] = 0;   // 优惠金额
        if (!isset($request['premium_amount'])) $request['premium_amount'] = 0;     // 溢价金额
        if (!isset($request['pre_sale_id'])) $request['pre_sale_id'] = 0;           // 售前客服ID

        // 添加手工订单、补发订单、重发订单, 线索单(售后，售前没有线索单)
        //if (!isset($request['type'])) $request['type'] = OrderManual::TYPE_NORMAL; // 默认常规单
        $data_arr['type'] = OrderManual::TYPE_NORMAL; // 常规单

        // 客户地址是否存在
        if (!$this->customer_address_repo) $this->customer_address_repo = new CustomerAddressRepository();
        // 这里先查地址库，再查客户表，因为手机号可能已经存在于地址库，是某用户的多个手机号中的一个，因此采用反向查找方式
        $customer_address_info = $this->customer_address_repo->getInfoByCountryIdAndTel($data_arr['country_id'], $data_arr['tel']);

        // 准备插入order表字段拼装
        $data_arr['month'] =  date('Ym'); // 分区
        $data_arr['order_source'] = 4;  // TODO 来源CRM ???? 增加一个可选值？
        $data_arr['order_type'] = 2;    // 售前手动
        $data_arr['order_second_type'] = $data_arr['type']; // 补发，重发，常规单
        $data_arr['order_scope'] = 1;   // 订单范围 1内部单2外部单(售前和售后的手工订单都属于内部订单)
        $data_arr['order_no'] = \generateOrderSn(); // 订单号，保存的时候，也生产订单号吧
        $data_arr['repeat_id'] = 0;  // int，如何找出来 TODO 计算规则????
        $data_arr['customer_id'] = $customer_address_info['customer_id'] ?? 0;  // 客户ID
        //$data_arr['customer_name'] = $request['customer_name'];  // 客户名称就是订单中的名称，提交过来的名称
        //$data_arr['tel'] = $request['tel'];   // 前面已经赋值，这里不再重新赋值，包括国家ID，省市区，语言等
        // @2020.4.24 钉钉群里面确认就是按照国家的货币
        $data_arr['currency'] = '';
        if (isset($data_arr['country_id']) && $data_arr['country_id']) {
            // 保存的时候，可能还没有选国家
            $country_info = (new CountryRepository())->getInfoById($data_arr['country_id']);
            if ($country_info && isset($country_info['code'])) {
                // 通过三字码找对应的货币
                $currency_info = (new CurrencyRepository())->getInfoByCode3($country_info['code']);
                if ($currency_info && isset($currency_info['code']))
                    $data_arr['currency'] = $currency_info['code'];
            }
        }

        // sale_amount 商品总金额，通过商品价格*num计算得出；
        $sale_amount = 0;
        if (isset($request['goods_info']) && $request['goods_info']) {
            foreach ($request['goods_info'] as $goods_info) {
                $sale_amount = bcadd($sale_amount, bcmul($goods_info['sell_price'], $goods_info['num']));
            }
        }
        $request['sale_amount'] = $sale_amount;
        $data_arr['sale_amount'] = $request['sale_amount'];  // 商品总金额
        // 其他金额在上面已经赋初值
        // 订单总金额为 = 商品总金额 + 溢价金额 - 优惠金额 - 预付金额
        $request['order_amount'] = $request['sale_amount'] + $request['premium_amount'] - $request['discount_amount'] - $request['received_amount'];
        $data_arr['order_amount'] = $request['order_amount'];
        $data_arr['order_time'] = $curr_datetime;
        $data_arr['order_long_time'] = strtotime($curr_datetime); //bcmul(strtotime($curr_datetime), 1000000, 0);
        $data_arr['distribute_status'] = 1; // 分配状态 0未分配1已分配，手工下单提交，直接分配给指定的客服
        $data_arr['distribute_time'] = $curr_datetime;
        $data_arr['audit_status'] = 1; // 审核状态 0未审核1已审核，手工下单直接审核通过
        $data_arr['audit_time'] = $curr_datetime;
        $data_arr['order_status'] = 22; // 已经审核
        $data_arr['shipping_status'] = 0; // 0没有物流信息 物流状态未上线 30 TODO
        $data_arr['invalid_status'] = 0; // 无效状态 0有效1系统判重2审核取消3审核重复
        $data_arr['department_id'] = $login_user_info['department_id'];
        // 超级管理员没有部门，则暂时用售前客服的部门id，上线后应该不会出现这个情况
        if (0 == $login_user_info['department_id']) {
            $pre_sale_info = (new UserRepository())->getUserById($request['pre_sale_id']);
            $data_arr['department_id'] = $pre_sale_info['department_id'];
        }

        // order_detail表中的字段暂时没有拼装，全是商品信息

        // order_manual表中的字段
        $data_arr['order_sale_id'] = $request['pre_sale_id'];  // 售前客服ID
        $data_arr['part'] = $request['pre_sale_id']%10;  //
        // department_id
        // order_id
        // order_no
        // type                             // 类别 1常规单2补发单3重发单4线索
        $data_arr['job_type'] = 1;          // 岗位类型 1售前2售后
        $data_arr['source_order_id'] = 0;   // 原订单id
        $data_arr['source_order_no'] = '';
        $data_arr['remark'] = $request['remark'] ?? '';
        $data_arr['status'] = 1;            //状态 0未提交1已提交-1已取消
        // opt_time

        $v_id = $this->insertOrderAndManualData($data_arr, $request, $curr_datetime, $current_uid);
        if (!$v_id) {
            ErrorMsg::FillResponseAndLog($responseDto, ErrorMsg::INSERT_DB_FAILED);
            return $responseDto;
        }

        // 记录操作日志
        $data_arr['opt_type_id'] = 1;
        $data_arr['optator_id'] = $current_uid;
        (new OrderOptRecordRepository())->insertOrderOptRecord($v_id, $data_arr);

        // 暂不返回详情，前端跳列表页
        $responseDto->data = ['id'=>$v_id];

        return $responseDto;
    }


    // 首次保存手工单
    private function insertSaveManualOrder($request, $data_arr, $curr_datetime, $current_uid) {
        $responseDto = new ResponseDto();

        $login_user_info = self::getCurrentLoginUserInfo();

        // 参数校验数组, 当前登录用户是否有权限暂不验证，后面统一处理
        $rules = [
            'status' => 'required|integer',       // 提交类型，1-提交 0-未提交；
            'order_id' => 'sometimes|integer|min:1',
        ];
        $validate = Validator::make($request, $rules);
        if ($validate->fails()) {
            $error_list = $validate->errors()->all();
            $responseDto->status = ErrorMsg::PARAM_ERROR;
            $responseDto->msg = implode("\r\n", $error_list);
            return $responseDto;
        }
        if (0 != $request['status']) {
            ErrorMsg::FillResponseAndLog($responseDto, ErrorMsg::ORDER_SUBMIT_TYPE_ERROR);
            return $responseDto;
        }
        // 保存的时候，数据可以提供不全
        if (!isset($request['collect_amount'])) $request['collect_amount'] = 0;     // 代收金额
        if (!isset($request['received_amount'])) $request['received_amount'] = 0;   // 预付金额
        if (!isset($request['discount_amount'])) $request['discount_amount'] = 0;   // 优惠金额
        if (!isset($request['premium_amount'])) $request['premium_amount'] = 0;     // 溢价金额
        if (!isset($request['pre_sale_id'])) $request['pre_sale_id'] = 0;           // 售前客服ID
        if (!isset($request['language_id'])) $request['language_id'] = 0;
        if (!isset($request['country_id'])) $request['country_id'] = 0;
        if (!isset($request['zone_prov_name'])) $request['zone_prov_name'] = '';
        if (!isset($request['zone_city_name'])) $request['zone_city_name'] = '';
        if (!isset($request['zone_area_name'])) $request['zone_area_name'] = '';
        //if (!isset($request['goods_info'])) $request['goods_info'] = [];
        if (!isset($request['customer_name'])) $request['customer_name'] = '';
        if (!isset($request['tel'])) $request['tel'] = '';

        $data_arr = array_merge($data_arr, $request);
        $data_arr['type'] = OrderManual::TYPE_NORMAL; // 常规单

        // 准备插入order表字段拼装
        $data_arr['month'] =  date('Ym'); // 分区
        $data_arr['order_source'] = 4;  // TODO 来源CRM ???? 增加一个可选值？
        $data_arr['order_type'] = 2;    // 售前手动
        $data_arr['order_second_type'] = $data_arr['type']; // 补发，重发，常规单
        $data_arr['order_scope'] = 1;   // 订单范围 1内部单2外部单(售前和售后的手工订单都属于内部订单)
        $data_arr['order_no'] = $data_arr['order_no'] ?? (\generateOrderSn()); // 订单号，保存的时候，也生产订单号吧
        $data_arr['repeat_id'] = 0;  // int，如何找出来 TODO 计算规则????
        $data_arr['customer_id'] = $customer_address_info['customer_id'] ?? 0;  // 客户ID
        //$data_arr['customer_name'] = $request['customer_name'];  // 客户名称就是订单中的名称，提交过来的名称
        //$data_arr['tel'] = $request['tel'];   // 前面已经赋值，这里不再重新赋值，包括国家ID，省市区，语言等
        // @2020.4.24 钉钉群里面确认就是按照国家的货币
        $data_arr['currency'] = '';

        // sale_amount 商品总金额，通过商品价格*num计算得出；
        $sale_amount = 0;
        if (isset($request['goods_info']) && $request['goods_info']) {
            foreach ($request['goods_info'] as $goods_info) {
                $sale_amount = bcadd($sale_amount, bcmul($goods_info['sell_price'], $goods_info['num']));
            }
        }
        $request['sale_amount'] = $sale_amount;
        $data_arr['sale_amount'] = $request['sale_amount'];  // 商品总金额
        // 其他金额在上面已经赋初值
        // 订单总金额为 = 商品总金额 + 溢价金额 - 优惠金额 - 预付金额
        $request['order_amount'] = $request['sale_amount'] + $request['premium_amount'] - $request['discount_amount'] - $request['received_amount'];
        $data_arr['order_amount'] = $request['order_amount'];
        $data_arr['order_time'] = $curr_datetime;
        $data_arr['order_long_time'] = strtotime($curr_datetime); //bcmul(strtotime($curr_datetime), 1000000, 0); // TODO bigint(20) ????
        $data_arr['distribute_status'] = 0; // 分配状态 0未分配1已分配，手工下单提交，直接分配给指定的客服
        $data_arr['distribute_time'] = $curr_datetime;
        $data_arr['audit_status'] = 0; // 审核状态 0未审核1已审核，手工下单直接审核通过
        $data_arr['audit_time'] = $curr_datetime;
        $data_arr['order_status'] = 1; // 已经审核，只是保存，还未处理
        $data_arr['shipping_status'] = 0; // 0没有物流信息 物流状态未上线 30 TODO
        $data_arr['invalid_status'] = 10; // 无效状态 0有效1系统判重2审核取消3审核重复
        $data_arr['department_id'] = $login_user_info['department_id'];
        // 超级管理员没有部门，则暂时用售前客服的部门id，上线后应该不会出现这个情况
        if (0 == $login_user_info['department_id'] && $request['pre_sale_id']) {
            $pre_sale_info = (new UserRepository())->getUserById($request['pre_sale_id']);
            $data_arr['department_id'] = $pre_sale_info['department_id'];
        }

        // order_detail表中的字段暂时没有拼装，全是商品信息

        // order_manual表中的字段
        $data_arr['order_sale_id'] = $request['pre_sale_id'] ?? 0;  // 售前客服ID
        $data_arr['part'] = $data_arr['order_sale_id'] % 10;  //
        // department_id
        // order_id
        // order_no
        // type                             // 类别 1常规单2补发单3重发单4线索
        $data_arr['job_type'] = 1;          // 岗位类型 1售前2售后
        $data_arr['source_order_id'] = 0;   // 原订单id
        $data_arr['source_order_no'] = '';
        $data_arr['remark'] = $request['remark'] ?? '';
        //$data_arr['status'] = 0;            //状态 0未提交1已提交-1已取消
        // opt_time

        $v_id = $this->insertOrderAndManualData($data_arr, $request, $curr_datetime, $current_uid);
        if (!$v_id) {
            ErrorMsg::FillResponseAndLog($responseDto, ErrorMsg::INSERT_DB_FAILED);
            return $responseDto;
        }

        // 记录操作日志
        $data_arr['opt_type_id'] = 2;
        $data_arr['optator_id'] = $current_uid;
        (new OrderOptRecordRepository())->insertOrderOptRecord($v_id, $data_arr);

        // 暂不返回详情，前端跳列表页
        $responseDto->data = ['id'=>$v_id];

        return $responseDto;
    }

    public function detail($id) {
        $request['order_id'] = $id;
        $responseDto = new ResponseDto();

        // uid参数校验; 当前登录用户是否有权限暂不验证，后面统一处理
        $field_id = 'order_id';
        $rules = [
            $field_id => 'required|integer|min:1'
        ];
        $validate = Validator::make($request, $rules);
        if ($validate->fails()) {
            $error_list = $validate->errors()->all();
            $responseDto->status = ErrorMsg::PARAM_ERROR;
            $responseDto->msg = implode("\r\n", $error_list);
            return $responseDto;
        }

        $v_detail = $this->theRepository->getInfoById($request[$field_id]);
        if (!$v_detail) {
            ErrorMsg::FillResponseAndLog($responseDto, ErrorMsg::DATA_EMPTY);
            return $responseDto;
        }
        $order_id_list = [$id];
        if ($v_detail['source_order_id']) {
            $order_id_list[] = $v_detail['source_order_id'];
        }
        // 通过 order_id，获取对应的订单详情数据
        $order_info_list = $this->orderRepository->getOrderInfosByOrderIds($order_id_list); // 批量获取
        // 通过 order_id，获取订单对应的商品详情数据
        $goods_info_list = $this->orderDetailRepository->getGoodsListByOrderIds($order_id_list); // 三维数组

        // 成功，返回信息
        $v_info = self::getOneOrderInfo($v_detail, $order_info_list, $goods_info_list);
        // 顺带返回原订单数据
        if ($v_detail['source_order_id'] && isset($order_info_list[$v_detail['source_order_id']]))
            $v_info['source_order_info'] = self::getSourceOrderInfo($order_info_list[$v_detail['source_order_id']], $goods_info_list);
        else $v_info['source_order_info'] = new \stdClass();

        $responseDto->data = $v_info;

        return $responseDto;
    }

    // 取消订单
    public function delete($id) {
        $responseDto = new ResponseDto();
        $request['order_id'] = $id;

        // 参数校验数组
        $rules = [
            'order_id' => 'required|integer|min:1'
        ];
        $validate = Validator::make($request, $rules);
        if ($validate->fails()) {
            $error_list = $validate->errors()->all();
            $responseDto->status = ErrorMsg::PARAM_ERROR;
            $responseDto->msg = implode("\r\n", $error_list);
            return $responseDto;
        }

        // 更新2张表，order_manual 和 order 表
        $request['status'] = '-1';  // 取消
        $this->theRepository->updateData($id, $request);
        $request['invalid_status'] = 10; // TODO  10-售前客服取消 ， 2-审核取消
        $this->orderRepository->updateData($id, $request);

        return $responseDto;
    }

    // 更新单条, 手工单，保存 -> 提交或继续保存
    public function updateOne($id) {
        $request = request()->all();
        $request['order_id'] = $id;
        $responseDto = new ResponseDto();

        // 参数校验数组, 当前登录用户是否有权限暂不验证，后面统一处理
        $rules = [
            'order_id' => 'required|integer|min:1'
        ];
        $validate = Validator::make($request, $rules);
        if ($validate->fails()) {
            $error_list = $validate->errors()->all();
            $responseDto->status = ErrorMsg::PARAM_ERROR;
            $responseDto->msg = implode("\r\n", $error_list);
            return $responseDto;
        }
        return self::addOrUpdate($request);
    }

    // 插入手工下单表和订单总表，需要进行事务处理
    public function insertOrderAndManualData($data_arr, $request, $create_time='', $creator_id=0) {
        if (1 == $request['submit_type']) {
            // 插入客户之前，先检查一下客户是否存在，以及地址库是否存在相同的记录，检查的步骤不要放到事务中
            if (!$this->customer_repo) $this->customer_repo = new CustomerRepository();
            if (!$this->customer_address_repo) $this->customer_address_repo = new CustomerAddressRepository();
            // 这里先查地址库，再查客户表，因为手机号可能已经存在于地址库，是某用户的多个手机号中的一个，因此采用反向查找方式
            $customer_info = $this->customer_repo->getInfoByCountryIdAndTel($data_arr['country_id'], $data_arr['tel']);
            $customer_address_info = $this->customer_address_repo->getInfoByCountryIdAndTel($data_arr['country_id'], $data_arr['tel']);
            if ($customer_address_info) {
                // 搜到了对应客户ID信息，还需要进一步确认，该收货信息是否完全匹配，因为客户姓名、详细地址哪怕加一个空格都算新地址
                $customer_address_info = $this->customer_address_repo->getInfoByMultiFields($data_arr); // 完成的信息请求
            }
        }

        \DB::beginTransaction();
        try {
            $order_id = $this->orderRepository->insertGetId($data_arr);
            if (!$order_id)
                return false;
            $data_arr['order_id'] = $order_id;
            if (!isset($data_arr['remark'])) $data_arr['remark'] = ''; // 该字段不能为空 ？？ TEXT类型？？
            $this->theRepository->insertGetId($data_arr);
            // 商品信息，还需要插入 order_detail 表
            if (isset($data_arr['goods_info']) && $data_arr['goods_info'])
                $this->orderDetailRepository->insertMultipleByOrderId($order_id, $data_arr['goods_info']);
            if (1 == $request['submit_type'] && !$customer_address_info) {
                // 提交, status=1 ，需要产生客户信息和客户地址信息
                if (!$customer_info) {
                    $customer_id = $this->customer_repo->insertGetId($this->customer_repo->formatDataForInsertByManualOrder($data_arr));
                    $customer_info = ['id' => $customer_id];
                }
                $this->customer_address_repo->insertGetId($this->customer_address_repo->formatDataForInsertByManualOrder($customer_info['id'], $data_arr));
                $this->customer_repo->updateData($customer_info['id'], $this->customer_repo->formatDataForUpdateByManualOrder($data_arr, $create_time, $creator_id));
            }
            \DB::commit();
        } catch (\Exception $e) {
            $msg = 'db-Transaction-error: table, order, order_manual' . ' error: ' . $e->getMessage() . ' data:';
            \Log::error($msg, $request);
            \DB::rollBack();
            return false;
        }

        // 删除统计缓存
        $this->orderRepository->deleteCacheByUidAndType($creator_id, 'manual');

        return $order_id;
    }

    // 更新手工下单表和订单总表，需要进行事务处理 TODO
    public function updateOrderAndManualData($data_arr, $request, $db_order_detail, $db_manual_order_detail, $create_time='', $creator_id=0) {
        if (0 == $request['status']) {
            // 继续保存的情况，只需要维护 order, order_detail, order_manual 3张表
            return $this->updateSaveManualOrder($data_arr, $request, $create_time, $creator_id);
        } else if (1 == $request['status']) {
            // 提交的情况，再走一遍手工创建订单的逻辑，可能需要创建新客户地址
            return $this->updateSubmitManualOrder($data_arr, $request, $db_order_detail, $db_manual_order_detail, $create_time, $creator_id);
        }
        return false;
    }

    // 保存（非提交）手工下单数据，继续保存，只需要维护 order, order_detail, order_manual 3张表
    public function updateSaveManualOrder($data_arr, $request, $create_time='', $creator_id=0) {
        $order_id = $request['order_id'];

        \DB::beginTransaction();
        try {
            $this->orderRepository->updateData($order_id, $data_arr, $create_time, $creator_id);
            $this->theRepository->updateData($order_id, $data_arr);
            $this->orderDetailRepository->updateMultiGoodsByOrderId($order_id, $data_arr); // 更新商品信息
            \DB::commit();
        } catch (\Exception $e) {
            $msg = 'db-Transaction-error: table, order, order_manual error: ' . $e->getMessage() . ' data:';
            \Log::error($msg, $request);
            \DB::rollBack();
            return false;
        }
        return true;
    }

    // 提交的情况，再走一遍手工创建订单的逻辑，可能需要创建新客户地址
    public function updateSubmitManualOrder($data_arr, $request, $db_order_detail, $db_manual_order_detail, $create_time='', $creator_id=0) {
        $order_id = $request['order_id'];
        $data_arr = $this->formatForUpdateSubmitManualOrder($data_arr, $request, $db_order_detail, $db_manual_order_detail);

        // 提交，需要生成客户信息
        if (1 == $request['status']) {
            // 插入客户之前，先检查一下客户是否存在，以及地址库是否存在相同的记录，检查的步骤不要放到事务中
            if (!$this->customer_repo) $this->customer_repo = new CustomerRepository();
            if (!$this->customer_address_repo) $this->customer_address_repo = new CustomerAddressRepository();
            // 这里先查地址库，再查客户表，因为手机号可能已经存在于地址库，是某用户的多个手机号中的一个，因此采用反向查找方式
            $customer_info = $this->customer_repo->getInfoByCountryIdAndTel($data_arr['country_id'], $data_arr['tel']);
            $customer_address_info = $this->customer_address_repo->getInfoByCountryIdAndTel($data_arr['country_id'], $data_arr['tel']);
            if ($customer_address_info) {
                // 搜到了对应客户ID信息，还需要进一步确认，该收货信息是否完全匹配，因为客户姓名、详细地址哪怕加一个空格都算新地址
                $customer_address_info = $this->customer_address_repo->getInfoByMultiFields($data_arr); // 完成的信息请求
            }
        }

        \DB::beginTransaction();
        try {
            $this->orderRepository->updateData($order_id, $data_arr, $create_time, $creator_id);
            $this->theRepository->updateData($order_id, $data_arr);
            $this->orderDetailRepository->updateMultiGoodsByOrderId($order_id, $data_arr); // 更新商品信息

            if (1 == $request['status'] && !$customer_address_info) {
                // 提交, status=1 ，需要产生客户信息和客户地址信息
                if (!$customer_info) {
                    $customer_id = $this->customer_repo->insertGetId($this->customer_repo->formatDataForInsertByManualOrder($data_arr));
                    $customer_info = ['id' => $customer_id];
                }
                $this->customer_address_repo->insertGetId($this->customer_address_repo->formatDataForInsertByManualOrder($customer_info['id'], $data_arr));
                $this->customer_repo->updateData($customer_info['id'], $this->customer_repo->formatDataForUpdateByManualOrder($data_arr, $create_time, $creator_id));
            }
            \DB::commit();
        } catch (\Exception $e) {
            $msg = 'db-Transaction-error: table, order, order_manual error: ' . $e->getMessage() . ' data:';
            \Log::error($msg, $request);
            \DB::rollBack();
            return false;
        }

        // 删除统计缓存
        $this->orderRepository->deleteCacheByUidAndType($creator_id, 'manual');

        return true;
    }

    // 补发 - 可选订单列表
    public function getReplenishList() {
        $request = request()->all();
        //$request['type'] = 1;                 // order_manual表
        $request['status'] = 1;                 // order_manual表, 已经提交
        $request['order_type'] = ['in', [1,2]]; // order表, 订单类型 1广告2售前手工3售后手工
        //$request['order_second_type'] = 1;  // order表 1常规单 2补发 3重发，补发可以对补发或重发订单进行再次补发
        $request['shipping_status'] = 9;    // order表 签收
        return self::getList($request);
    }

    // 重发 - 可选订单列表
    public function getRedeliveryList() {
        $request = request()->all();
        //$request['type'] = 1;               // order_manual表
        $request['status'] = 1;                 // order_manual表, 已经提交
        $request['order_type'] = ['in', [1,2]]; // order表, 订单类型 1广告2售前手工3售后手工
        //$request['order_second_type'] = 1;  // order表 1常规单 2补发 3重发，重发可以对补发或重发订单进行再次重发
        //$request['order_status'] = 18;      // order表 已经出库
        $request['shipping_status'] = ['notin', [9, 16]];      // order表 拒收状态才可以
        return self::getList($request);
    }

    // 添加补发
    public function addReplenish($id) {
        $request = request()->all();
        $request['source_order_id'] = $id; // 原订单ID
        $request['job_type'] = 1;       // 售前
        $request['type'] = OrderManual::TYPE_REPLENISH;

        $responseDto = new ResponseDto();

        //$login_user_info = self::getCurrentLoginUserInfo();
        if ('cli' != php_sapi_name()) $current_uid = auth('api')->id();
        else $current_uid = ($request['creator_id'] ?? 0) + 0;
        $curr_datetime = date('Y-m-d H:i:s');

        // 参数校验数组, 当前登录用户是否有权限暂不验证，后面统一处理
        $rules = [
            'source_order_id' => 'required|integer|min:1',
            'remark' => 'required|string',
            'goods_info' => 'required',
            'submit_type' => 'required|integer|min:1', // 提交类型，1-提交 2-保存；
        ];
        $validate = Validator::make($request, $rules);
        if ($validate->fails()) {
            $error_list = $validate->errors()->all();
            $responseDto->status = ErrorMsg::PARAM_ERROR;
            $responseDto->msg = implode("\r\n", $error_list);
            return $responseDto;
        }
        // 获取元订单数据对应数据
        $v_order_detail = $this->orderRepository->getInfoById($request['source_order_id']);
        if (!$v_order_detail || !isset($v_order_detail['order_status']) || !isset($v_order_detail['shipping_status'])) {
            ErrorMsg::FillResponseAndLog($responseDto, ErrorMsg::DATA_NOT_EXISTS);
            return $responseDto;
        }

        // 原订单信息，需要有物流信息, 需要出库
        if (18 != $v_order_detail['order_status']) {
            ErrorMsg::FillResponseAndLog($responseDto, ErrorMsg::ORDER_STATUS_ERROR);
            return $responseDto;
        }
        if (!in_array($v_order_detail['shipping_status'], [9, 16])) { // 已签收或拒收状态的才可以
            ErrorMsg::FillResponseAndLog($responseDto, ErrorMsg::ORDER_STATUS_ERROR);
            return $responseDto;
        }
        // 必须包含商品信息
        if (!isset($request['goods_info']) || !$request['goods_info']) {
            ErrorMsg::FillResponseAndLog($responseDto, ErrorMsg::ORDER_ON_GOODS);
            return $responseDto;
        }
        if (1 == $request['submit_type']) {
            // 提交, status=1 ，需要产生客户信息，还有客户地址信息
            $request['status'] = 1;
        } else if (2 == $request['submit_type']) {
            // 保存, status=0，未提交
            $request['status'] = 0;
        } else $request['status'] = -1;

        // 不能重复提交多次补发申请，先判断是否存在未流转完成的补发订单，才能继续提交或保存
        $exists_db = $this->theRepository->getReplenishBySourceOrderId($id);
        if ($exists_db) {
            foreach ($exists_db as $exists_replenish) {
                if (-1 == $exists_replenish['status']) continue; // 取消状态的不用管
                if (0 == $exists_replenish['status']) { // 保存，未提交
                    // 已经有保存状态的，需要先处理保存状态的
                    ErrorMsg::FillResponseAndLog($responseDto, ErrorMsg::ORDER_REPLENIDH_HAS_EXISTS);
                    return $responseDto;
                }
                // 剩下提交状态的
                $exists_replenish_order = $this->orderRepository->getInfoById($exists_replenish['order_id']);
                if ($exists_replenish_order && 9 != $exists_replenish_order['order_status']) {
                    // 还未流转完成的，不能进行重复添加
                    ErrorMsg::FillResponseAndLog($responseDto, ErrorMsg::ORDER_REPLENIDH_HAS_EXISTS);
                    return $responseDto;
                }
            }
        }

        // 补发的商品数量进行核对
        $orig_goods_info = $this->orderDetailRepository->getGoodsListByOrderId($request['source_order_id']);
        if (!$orig_goods_info) {
            ErrorMsg::FillResponseAndLog($responseDto, ErrorMsg::ORDER_ON_GOODS);
            return $responseDto;
        }
        $sku_ids_old = array_column($orig_goods_info, 'sku');
        $sku_ids_new = array_column($request['goods_info'], 'sku');
        $add_ids = array_diff($sku_ids_new, $sku_ids_old); // 在新的不在旧的里面，说明商品不对应
        if ($add_ids) {
            ErrorMsg::FillResponseAndLog($responseDto, ErrorMsg::ORDER_REPLENIDH_GOODS_OVER);
            return $responseDto;
        }

        // 逐个商品数量校对 TODO 需要累加之前所有补发的商品数量，这里先简单处理
        $orig_goods_info = array_column($orig_goods_info, null, 'sku');
        foreach ($request['goods_info'] as $row) {
            if ($row['num'] > $orig_goods_info[$row['sku']]['num']) {
                ErrorMsg::FillResponseAndLog($responseDto, ErrorMsg::ORDER_REPLENIDH_GOODS_OVER); // 商品数量
                return $responseDto;
            }
        }

        // 商品数量不能超过原订单，补发虽然没有次数限制，但是每种商品的补发数量不得超过原订单对应商品数量；补发不能加新商品，只能原商品
        $v_id = $this->insertReplenish($request, $v_order_detail, $orig_goods_info, $curr_datetime, $current_uid);
        if (!$v_id) {
            ErrorMsg::FillResponseAndLog($responseDto, ErrorMsg::INSERT_DB_FAILED);
            return $responseDto;
        }
        $responseDto->data = ['id'=>$v_id];
        return $responseDto;
    }

    // 添加重发
    public function addRedelivery($id) {
        $request = request()->all();
        $request['source_order_id'] = $id; // 原订单ID
        $request['job_type'] = 1;       // 售前
        $request['type'] = OrderManual::TYPE_REDELIVERY;

        $responseDto = new ResponseDto();

        //$login_user_info = self::getCurrentLoginUserInfo();
        if ('cli' != php_sapi_name()) $current_uid = auth('api')->id();
        else $current_uid = ($request['creator_id'] ?? 0) + 0;
        $curr_datetime = date('Y-m-d H:i:s');

        // 参数校验数组, 当前登录用户是否有权限暂不验证，后面统一处理
        $rules = [
            'source_order_id' => 'required|integer|min:1',
            'remark' => 'required|string',
        ];
        $validate = Validator::make($request, $rules);
        if ($validate->fails()) {
            $error_list = $validate->errors()->all();
            $responseDto->status = ErrorMsg::PARAM_ERROR;
            $responseDto->msg = implode("\r\n", $error_list);
            return $responseDto;
        }
        // 获取元订单数据对应数据
        $v_order_detail = $this->orderRepository->getInfoById($request['source_order_id']);
        if (!$v_order_detail || !isset($v_order_detail['order_status']) || !isset($v_order_detail['shipping_status'])) {
            ErrorMsg::FillResponseAndLog($responseDto, ErrorMsg::DATA_NOT_EXISTS);
            return $responseDto;
        }

        // 原订单信息，需要有物流信息, 需要出库
        if (18 != $v_order_detail['order_status']) {
            ErrorMsg::FillResponseAndLog($responseDto, ErrorMsg::ORDER_STATUS_ERROR);
            return $responseDto;
        }
        if ($v_order_detail['shipping_status'] <= 0) {
            ErrorMsg::FillResponseAndLog($responseDto, ErrorMsg::ORDER_STATUS_ERROR);
            return $responseDto;
        }

        // 不能重复提交多次重发申请，先判断是否存在未流转完成的重发订单，才能继续提交或保存
        $exists_db = $this->theRepository->getReDeliveryBySourceOrderId($id);
        if ($exists_db) {
            foreach ($exists_db as $exists_redelivery) {
                if (-1 == $exists_redelivery['status']) continue; // 取消状态的不用管
                if (0 == $exists_redelivery['status']) { // 保存，未提交
                    // 已经有保存状态的，需要先处理保存状态的
                    ErrorMsg::FillResponseAndLog($responseDto, ErrorMsg::ORDER_REPLENIDH_HAS_EXISTS);
                    return $responseDto;
                }
                // 剩下提交状态的
                $exists_redelivery_order = $this->orderRepository->getInfoById($exists_redelivery['order_id']);
                if ($exists_redelivery_order && !in_array($exists_redelivery_order['order_status'], [9, 16])) {
                    // 还未流转完成的，不能进行重复添加
                    ErrorMsg::FillResponseAndLog($responseDto, ErrorMsg::ORDER_REDELIVERY_HAS_EXISTS);
                    return $responseDto;
                }
            }
        }

        // 重发，没有次数限制，可以无限重发
        $v_id = $this->insertRedelivery($request, $v_order_detail, $curr_datetime, $current_uid);
        if (!$v_id) {
            ErrorMsg::FillResponseAndLog($responseDto, ErrorMsg::INSERT_DB_FAILED);
            return $responseDto;
        }
        $responseDto->data = ['id'=>$v_id];
        return $responseDto;
    }

    // 重发插入, 需要事务
    public function insertRedelivery($request, $v_order_detail, $created_time, $creator_id) {
        // order表修改 order_second_type 为3
        $order_update_arr = ['order_second_type' => 3];

        // order_manual表插入一条数据， 拼装数据，准备入库
        $data_arr = $request;
        $data_arr['source_order_id'] = $v_order_detail['id'];           // 原订单id
        $data_arr['source_order_no'] = $v_order_detail['order_no'];     // 原订单号
        $data_arr['order_sale_id'] = $v_order_detail['pre_sale_id'];
        $data_arr['part'] = $data_arr['order_sale_id'] % 10;
        $data_arr['department_id'] = $v_order_detail['department_id'];
        $data_arr['order_id'] = $v_order_detail['id'];
        $data_arr['order_no'] = $v_order_detail['order_no'] . '_'. \createOrderNoSequenceNo($v_order_detail['order_no']);
        $data_arr['type'] = OrderManual::TYPE_REDELIVERY;
        $data_arr['job_type'] = 1;                                      // 售前
        $data_arr['status'] = 1;                                        // 已提交
        $data_arr['creator_id'] = $creator_id;
        $data_arr['updator_id'] = $data_arr['creator_id'];
        $data_arr['created_time'] = $created_time;
        $data_arr['updated_time'] = $data_arr['created_time'];
        //$data_arr['remark'];

        \DB::beginTransaction();
        try {
            $this->orderRepository->updateData($data_arr['order_id'], $order_update_arr);
            $v_id = $this->theRepository->insertGetId($data_arr);
            \DB::commit();
        } catch (\Exception $e) {
            $msg = 'db-Transaction-error: table, order, order_manual error: ' . $e->getMessage() . ' data:';
            \Log::error($msg, $request);
            \DB::rollBack();
            return false;
        }

        // 删除统计缓存
        $this->orderRepository->deleteCacheByUidAndType($creator_id, 'manual');

        return $v_id;
    }

    // 补发插入, 需要事务
    public function insertRePlenish($request, $v_order_detail, $orig_goods_info, $created_time, $creator_id) {
        // 需要创建一条新订单，客户信息不用创建，只需要order,order_detail,order_manual 3张表
        // order_manual表插入一条数据， 拼装数据，准备入库
        $data_arr = $v_order_detail;
        unset($data_arr['id']);

        // order表中字段，需要重新字段商品价格
        $data_arr = $this->formatForUpdateSubmitManualOrder($data_arr, $request, $v_order_detail, []);
        //$data_arr['order_amount'] = 0; // TODO 订单总金额，代收金额都为0 ?
        $data_arr['collect_amount'] = 0; // 补发订单，不再收取客户费用

        // order_manual 表中字段
        $data_arr['type'] = OrderManual::TYPE_REPLENISH;
        $data_arr['job_type'] = 1;                                      // 售前
        $data_arr['source_order_id'] = $v_order_detail['id'];           // 原订单id
        $data_arr['source_order_no'] = $v_order_detail['order_no'];     // 原订单号
        $data_arr['order_sale_id'] = $v_order_detail['pre_sale_id'];
        $data_arr['part'] = $data_arr['order_sale_id'] % 10;
        $data_arr['department_id'] = $v_order_detail['department_id'];
        $data_arr['order_id'] = $v_order_detail['id'];
        $data_arr['order_no'] = \generateOrderSn();                     // 补发订单，需要重新生成订单号
        $data_arr['status'] = $request['status'];                       // 可能是保存，也可能是提交
        $data_arr['creator_id'] = $creator_id;
        $data_arr['updator_id'] = $data_arr['creator_id'];
        $data_arr['created_time'] = $created_time;
        $data_arr['updated_time'] = $data_arr['created_time'];
        $data_arr['remark'] = $request['remark'];
        // $data_arr['goods_info'] = $request['goods_info']; // order_detail 表

        return $this->insertOrderAndManualData($data_arr, $request);
    }
}
