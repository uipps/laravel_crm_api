<?php

namespace App\Services\OrderPreSale;

use App\Dto\CountryOrderDto;
use App\Dto\MainPanelDto;
use App\Dto\OrderOptRecordDto;
use App\Dto\OrderStatsPreSaleDto;
use App\Libs\Utils\ErrorMsg;
use App\Dto\DataListDto;
use App\Dto\ResponseDto;
use App\Dto\OrderDto;
use App\Models\Admin\User;
use App\Models\OrderPreSale\Order;
use App\Models\OrderPreSale\OrderManual;
use App\Repositories\Admin\CountryRepository;
use App\Repositories\Admin\DepartmentRepository;
use App\Repositories\OrderPreSale\OrderOptRecordRepository;
use App\Repositories\OrderPreSale\OrderRepository;
use App\Repositories\Admin\UserRepository;
use App\Services\BaseService;
use Illuminate\Support\Facades\Validator;
use App\Repositories\OrderPreSale\OrderDetailRepository;
use App\Dto\OrderDetailDto;
use Illuminate\Support\Arr;

use function PHPSTORM_META\elementType;


class OrderService extends BaseService
{
    protected $theRepository;
    protected $departmentRepository;
    protected $orderDetailRepository; // 就是商品信息

    public function __construct()
    {
        $this->theRepository = new OrderRepository();
        $this->orderDetailRepository = new OrderDetailRepository();
        $this->userRepository = new UserRepository(); // 用于权限检查
    }

    public function getList($request=null)
    {
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
        // 订单状态的限制
        if (!isset($request['customer_id']) || !$request['customer_id']) {
            $request['order_type'] = Order::ORDER_TYPE_ADS;                     // 这里的列表，只显示广告单，不显示手工单（包括售前、售后）
            $request['order_second_type'] = Order::ORDER_SECOND_TYPE_NORMAL;    // 这里的列表，只显示常规单，不显示补发和重发
            $request['order_status'] = ['>', 0];                                // 订单状态，必须给一个值 1以上的值
        }

        // 主管能看到本部门的全部列表；员工只能看到自己的
        $login_user_info = self::getCurrentLoginUserInfo();
        // 如果是管理员，能看到所有
        $child_dept_list = [];
        if (1 != $login_user_info['role_id'] && !isset($request['customer_id'])) {
            if (User::LEVEL_ADMIN == $login_user_info['level']) {
                // 主管能看到本部门的全部列表
                $child_dept_list = parent::getChildrenDepartmentByDeptId($login_user_info['department_id']);
                if (!isset($request['department_id'])) $request['department_id'] = ['in', $child_dept_list];
            } else if (User::LEVEL_STAFF == $login_user_info['level']) {
                // 员工只能看到自己的
                if (!isset($request['pre_sale_id'])) $request['pre_sale_id'] = $login_user_info['id'];
            }
        }
        // 只显示售前：TODO

        // 获取数据，包含总数字段
        /*$list = $this->theRepository->getList($request);
        if (!$list || !isset($list[$responseDto::DTO_FIELD_TOTOAL]) || !isset($list[$responseDto::DTO_FIELD_LIST])) {
            ErrorMsg::FillResponseAndLog($responseDto, ErrorMsg::DATA_EMPTY);
            return $responseDto;
        }
        if ($list[$responseDto::DTO_FIELD_LIST]) {
            // 所有的 order_id 列表
            $order_ids = [];
            foreach ($list[$responseDto::DTO_FIELD_LIST] as $v_detail) {
                $order_ids[] = $v_detail['id'];
            }
            // 通过 order_id，获取订单对应的商品详情数据
            $goods_info_list = $this->orderDetailRepository->getGoodsListByOrderIds($order_ids); // 三维数组

            // 成功，返回列表信息
            foreach ($list[$responseDto::DTO_FIELD_LIST] as $key => $v_detail) {
                $list[$responseDto::DTO_FIELD_LIST][$key] = self::getOneOrderInfo($v_detail, $goods_info_list);
            }
        }
        $data_list = new DataListDto();
        if (!isset($request[$responseDto::WITHOUT_ORDER_STATS]) || !$request[$responseDto::WITHOUT_ORDER_STATS]) {
            // 默认情况，所有订单列表都要带上订单统计数据；如果设置不需要携带统计数据则跳过
            $redis_stats_data = $this->theRepository->getOrderNumStatsByUserId($login_user_info['id'], $login_user_info, $child_dept_list);
            // var_dump($redis_stats_data);
            $order_stat = new OrderStatsPreSaleDto();
            $order_stat->Assign($redis_stats_data); // 从redis缓存获取数据
            $data_list->meta[$responseDto::DTO_FIELD_ORDER_STATS] = $order_stat;
        }
        $data_list->Assign($list);
        $responseDto->data = $data_list;

        return $responseDto;*/
        return $this->getOrderList($request, $login_user_info, $child_dept_list);
    }
    private function getOrderList($request, $login_user_info, $child_dept_list) {
        $responseDto = new ResponseDto();

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
                $order_ids[] = $v_detail['id'];
            }
            // 通过 order_id，获取订单对应的商品详情数据
            $goods_info_list = $this->orderDetailRepository->getGoodsListByOrderIds($order_ids); // 三维数组

            // 成功，返回列表信息
            foreach ($list[$responseDto::DTO_FIELD_LIST] as $key => $v_detail) {
                $list[$responseDto::DTO_FIELD_LIST][$key] = self::getOneOrderInfo($v_detail, $goods_info_list);
            }
        }
        $data_list = new DataListDto();
        if (!isset($request[$responseDto::WITHOUT_ORDER_STATS]) || !$request[$responseDto::WITHOUT_ORDER_STATS]) {
            // 默认情况，所有订单列表都要带上订单统计数据；如果设置不需要携带统计数据则跳过
            $redis_stats_data = $this->theRepository->getOrderNumStatsByUserId($login_user_info['id'], $login_user_info, $child_dept_list);
            // var_dump($redis_stats_data);
            $order_stat = new OrderStatsPreSaleDto();
            $order_stat->Assign($redis_stats_data); // 从redis缓存获取数据
            $data_list->meta[$responseDto::DTO_FIELD_ORDER_STATS] = $order_stat;
        }
        $data_list->Assign($list);
        $responseDto->data = $data_list;

        return $responseDto;
    }

    public function addOrUpdate($request = null)
    {
        if (!$request) $request = request()->all();
        $responseDto = new ResponseDto();

        if ('cli' != php_sapi_name()) $current_uid = auth('api')->id();
        else $current_uid = ($request['creator_id'] ?? 0) + 0;
        // 参数校验数组, 当前登录用户是否有权限暂不验证，后面统一处理
        //$field_id = 'id';
        $rules = [
            'id' => 'sometimes|integer',
        ];
        $validate = Validator::make($request, $rules);
        if ($validate->fails()) {
            $error_list = $validate->errors()->all();
            $responseDto->status = ErrorMsg::PARAM_ERROR;
            $responseDto->msg = implode("\r\n", $error_list);
            return $responseDto;
        }

        $curr_datetime = date('Y-m-d H:i:s');
        $data_arr = $request; // 全部作为
        if (isset($request['id']) && $request['id']) {
            // 修改的情况
            $data_arr['id'] = $request['id'];
            // 检查该记录是否存在
            $v_detail = $this->theRepository->getInfoById($request['id']);
            if (!$v_detail) {
                ErrorMsg::FillResponseAndLog($responseDto, ErrorMsg::DATA_NOT_EXISTS);
                return $responseDto;
            }
            $data_arr['updator_id'] = $current_uid;
            //$data_arr['deleted_time'] = $data_arr['deleted_time'] ?? $this->theRepository::DATETIME_NOT_NULL_DEFAULT;
        } else {
            // 新增，注：有些需要检查对应的唯一key是否存在
            //$v_detail = $this->theRepository->getByUniqueKey($request);
            //if ($v_detail) {
            //    ErrorMsg::FillResponseAndLog($responseDto, ErrorMsg::DATA_EXISTS);
            //    return $responseDto;
            //}
            $data_arr['creator_id'] = $current_uid;
            $data_arr['updator_id'] = $data_arr['creator_id'];
            $data_arr['created_time'] = $curr_datetime;
            //$data_arr['deleted_time'] = $this->theRepository::DATETIME_NOT_NULL_DEFAULT;
        }
        // 数据增加几个默认值
        $data_arr['updated_time'] = $curr_datetime;

        if (isset($request['id']) && $request['id']) {
            // 更新
            $rlt = $this->theRepository->updateData($request['id'], $data_arr);
            if (!$rlt) {
                ErrorMsg::FillResponseAndLog($responseDto, ErrorMsg::UPDATE_DB_FAILED);
                return $responseDto;
            }
        } else {
            $v_id = $this->theRepository->insertGetId($data_arr);
            if (!$v_id) {
                ErrorMsg::FillResponseAndLog($responseDto, ErrorMsg::INSERT_DB_FAILED);
                return $responseDto;
            }
            // 暂不返回详情，前端跳列表页
            $responseDto->data = ['id' => $v_id];
        }
        return $responseDto;
    }

    public function detail($id)
    {
        $request['id'] = $id;
        $responseDto = new ResponseDto();

        // uid参数校验; 当前登录用户是否有权限暂不验证，后面统一处理
        $field_id = 'id';
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
        // 通过 order_id，获取订单对应的商品详情数据
        $goods_info_list = $this->orderDetailRepository->getGoodsListByOrderIds([$id]); // 三维数组
        // 通过 order_id，获取订单的操作记录
        //$order_opt_records = (new OrderOptRecordRepository())->getOrderOptRecordsByOrderId($id);
        $order_opt_records = [];

        // 成功，返回信息
        $v_info = self::getOneOrderInfo($v_detail, $goods_info_list, $order_opt_records);
        $responseDto->data = $v_info;

        return $responseDto;
    }

    public function delete($id)
    {
        // 进行软删除，更新状态即可
        $request['status'] = '-1';
        $request['id'] = $id;
        return self::addOrUpdate($request);
    }

    // 更新单条
    /*public function updateOne($id)
    {
        $request = request()->all();
        $request['id'] = $id;
        $responseDto = new ResponseDto();

        // 参数校验数组, 当前登录用户是否有权限暂不验证，后面统一处理
        $rules = [
            'id' => 'required|integer|min:1'
        ];
        $validate = Validator::make($request, $rules);
        if ($validate->fails()) {
            $error_list = $validate->errors()->all();
            $responseDto->status = ErrorMsg::PARAM_ERROR;
            $responseDto->msg = implode("\r\n", $error_list);
            return $responseDto;
        }
        return self::addOrUpdate($request);
    }*/

    // 控制面板，订单统计
    public function mainPanel() {
        // 参数接收
        $request = request()->all();
        $responseDto = new ResponseDto();

        $rules = [
            'month' => 'sometimes|integer|min:0'
        ];
        $validate = Validator::make($request, $rules);
        if ($validate->fails()) {
            $error_list = $validate->errors()->all();
            $responseDto->status = ErrorMsg::PARAM_ERROR;
            $responseDto->msg = implode("\r\n", $error_list);
            return $responseDto;
        }

        // 默认本月；
        $year = date('Y');
        if (!isset($request['month']) || ($request['month'] <= 0)) {
            $month = date('m');
        } else {
            $month = $request['month'];
        }
        $start_time = date('Y-m-d H:i:s', strtotime($year . '-' . $month . '-01 00:00:00'));
        $end_time = date('Y-m-d H:i:s', strtotime($year . '-' . ($month + 1) . '-01 00:00:00') - 1);

        // 主管能看到本部门的全部列表；员工只能看到自己的
        $login_user_info = self::getCurrentLoginUserInfo();
        // 如果是管理员，能看到所有
        $child_dept_list = [];
        if (1 != $login_user_info['role_id']) {
            if (User::LEVEL_ADMIN == $login_user_info['level']) {
                // 主管能看到本部门的全部列表
                $child_dept_list = parent::getChildrenDepartmentByDeptId($login_user_info['department_id']);

            } else if (User::LEVEL_STAFF == $login_user_info['level']) {
                // 员工只能看到自己的
                if (!isset($request['pre_sale_id'])) $request['pre_sale_id'] = $login_user_info['id'];
            }
        }

        $data = new MainPanelDto();

        // 获取该时间段，所有的订单， 然后按照国家归类统计
        $order_data = $this->theRepository->getOrderByUserAndTime($login_user_info, $child_dept_list, [$start_time, $end_time]);
        if (!$order_data) {
            $responseDto->data = $data;
            return $responseDto;
        }

        // 依据身份，显示不同数据，如果是员工，总订单数是分配到的订单总是，包括已经审核完和未审核的，audit_status字段判断
        $data_arr = [];
        $orderout_by_country = [];
        if (User::LEVEL_STAFF == $login_user_info['level']) {
            // 员工只能看到自己的
        } else {
            // 主管或管理员，看到手下的完成情况
        }
        $data_arr['order_total'] = 0;
        $data_arr['order_finished'] = 0;
        $data_arr['order_unfinished'] = 0;

        $data_arr['order_upsales'] = 0;
        $data_arr['orderout_signed'] = 0;
        $data_arr['orderout_rejected'] = 0;
        $data_arr['orderout_delivering'] = 0;
        $data_arr['orderout_sign_rate'] = '0.00'; // 计算得出

        foreach ($order_data as $one_order) {
            if (1 == $one_order['audit_status']) {
                // 已经审核完成
                $data_arr['order_finished'] += 1;
            } else {
                // 未审核完成
                $data_arr['order_unfinished'] += 1;
            }

            // 按照国家ID归类
            //if (!isset($orderout_by_country[$one_order['country_id']])) {
                $orderout_by_country[$one_order['country_id']][] = $one_order;
            //}
        }
        $data_arr['order_total'] = $data_arr['order_finished'] + $data_arr['order_unfinished'];

        $country_all = (new CountryRepository())->getAllCountry(); // 国家数据

        // 按照国家归类，多个国家的数据
        if ($orderout_by_country) {
            $l_orderout_by_country = [];

            foreach ($orderout_by_country as $country_id => $order_list) {
                $country_order_arr = [];
                $country_order_arr['country_id'] = $country_id;
                $country_order_arr['country_name'] = Arr::get($country_all, $country_id.'.display_name');

                $country_order_arr['order_upsales'] = 0;
                $country_order_arr['orderout_signed'] = 0;
                $country_order_arr['orderout_rejected'] = 0;
                $country_order_arr['orderout_delivering'] = 0;
                $country_order_arr['orderout_sign_rate'] = '0.00'; // 计算得出

                // 统计对应数量: 已签收 / Upsales订单 / 拒收 / 未签收 / 签收率
                foreach ($order_list as $one_order) {
                    // Upsales订单
                    if (in_array($one_order['pre_opt_type'], [13, 14])) {
                        $country_order_arr['order_upsales'] += 1;
                        $data_arr['order_upsales']++;     // 合计
                    }

                    if (9 == $one_order['shipping_status']) {
                        // 已签收
                        $country_order_arr['orderout_signed'] += 1;
                        $data_arr['orderout_signed']++;     // 合计
                    } else if (16 == $one_order['shipping_status']) {
                        // 拒收
                        $country_order_arr['orderout_rejected'] += 1;
                        $data_arr['orderout_rejected']++;
                    } else if (in_array($one_order['shipping_status'], [8, 30])) {
                        // 配送中
                        $country_order_arr['orderout_delivering'] += 1;
                        $data_arr['orderout_delivering']++;
                    }
                }
                $l_total = $country_order_arr['orderout_delivering'] + $country_order_arr['orderout_rejected'] + $country_order_arr['orderout_signed'];
                if (0 != $l_total) {
                    $country_order_arr['orderout_sign_rate'] = bcdiv(100*$country_order_arr['orderout_signed'], $l_total, 2);
                }

                $country_order = new CountryOrderDto();
                $country_order->Assign($country_order_arr);
                $l_orderout_by_country[] = $country_order;
            }
            $data->orderout_by_country = $l_orderout_by_country;

            // 总签收率
            $l_total = $data_arr['orderout_delivering'] + $data_arr['orderout_rejected'] + $data_arr['orderout_signed'];
            if (0 != $l_total) {
                $data_arr['orderout_sign_rate'] = bcdiv(100*$data_arr['orderout_signed'], $l_total, 2);
            }
        }

        $data->Assign($data_arr);

        $responseDto->data = $data;
        return $responseDto;
    }

    // 客户订单列表，只要customer_id大于0就行
    public function customerOrderList() {
        $request = request()->all();
        $request['customer_id'] = ['>', 0];
        return self::getList($request);
    }
    // 查询某客户的订单列表, 需要剔除只保存、未提交的手工单
    public function customerIdOrderList($customer_id) {
        $request = request()->all();
        $request['customer_id'] = $customer_id;
        //$request['shipping_status'] = ['IN', [9, 16]];  // 客户订单列表，只显示签收、拒收的订单 @2020.5.29 产品确认
        // @2020.5.29 产品找业务再次确认：需要剔除只保存、未提交的手工单

        // 主管能看到本部门的全部列表；员工只能看到自己的
        $login_user_info = self::getCurrentLoginUserInfo();
        // 如果是管理员，能看到所有
        $child_dept_list = [];
        if (1 != $login_user_info['role_id'] && !isset($request['customer_id'])) {
            if (User::LEVEL_ADMIN == $login_user_info['level']) {
                // 主管能看到本部门的全部列表
                $child_dept_list = parent::getChildrenDepartmentByDeptId($login_user_info['department_id']);
                if (!isset($request['department_id'])) $request['department_id'] = ['in', $child_dept_list];
            } else if (User::LEVEL_STAFF == $login_user_info['level']) {
                // 员工只能看到自己的
                if (!isset($request['pre_sale_id'])) $request['pre_sale_id'] = $login_user_info['id'];
            }
        }
        // 查询order表
        $order_list = Order::where($request)->get(['id'])->toArray();
        if (!$order_list)
            return self::getOrderList($request, $login_user_info, $child_dept_list);
        $order_ids = array_column($order_list, 'id');

        // 再去手工单表，剔除状态为未提交的
        $order_delete_manual = OrderManual::whereIn('order_id', $order_ids)->where('status', 0)->get(['id', 'order_id'])->toArray(); // 未提交
        if (!$order_delete_manual) {
            $order_act_ids = $order_ids;    // 没有需要剔除的
        } else {
            $delete_ids = array_column($order_delete_manual, 'order_id');
            $order_act_ids = array_diff($order_ids, $delete_ids);           // 计算差集，剔除了保存未提交的
        }

        $params['id'] = ['IN', $order_act_ids];
        return self::getOrderList($params, $login_user_info, $child_dept_list);
    }

    // 订单详情
    private function getOneOrderInfo($v_detail, $goods_info_list, $order_opt_records=[]) {
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

    // 员工关联订单
    public function getListByUser() {
        $request = request()->all(); // 参数接收
        $responseDto = new ResponseDto();

        // 参数校验数组
        $rules = [
            'user_id' => 'required|integer|min:1',
        ];
        $validate = Validator::make($request, $rules);
        if ($validate->fails()) {
            $error_list = $validate->errors()->all();
            $responseDto->status = ErrorMsg::PARAM_ERROR;
            $responseDto->msg = implode("\r\n", $error_list);
            return $responseDto;
        }
        //$login_user_info = self::getCurrentLoginUserInfo();
        $user_info = $this->userRepository->getUserById($request['user_id']);
        // 依据员工是售前还是售后，给定字段：
        if (1 != $user_info['role_id']) {
            if (!$this->departmentRepository) $this->departmentRepository = new DepartmentRepository();
            $department_info = $this->departmentRepository->getInfoById($user_info['department_id']);
            if (1 == $department_info['job_type']) {
                // 售前部门
                $request['pre_sale_id'] = $request['user_id'];  // 映射字段
            } else if (2 == $department_info['job_type']) {
                $request['after_sale_id'] = $request['user_id'];  // 映射字段
            }
        }
        //$request['pre_sale_id'] = $request['user_id'];  // 映射字段
        $request['without_order_stat'] = 1;
        unset($request['user_id']);

        return self::getOrderList($request, $user_info, []);
    }

    // 将员工订单转移给其他员工（可能是流转完成，也可能是未处理完成的）
    public function orderTransfer() {
        $request = request()->all();
        $responseDto = new ResponseDto();

        $login_user_info = self::getCurrentLoginUserInfo();
        if (!$login_user_info || User::LEVEL_ADMIN != $login_user_info['level']) {
            // 只有主管才可以
            ErrorMsg::FillResponseAndLog($responseDto, ErrorMsg::ORDER_OPT_NEED_ADMIN);
            return $responseDto;
        }

        // 参数校验数组, 当前登录用户是否有权限暂不验证，后面统一处理
        $rules = [
            'order_ids' => 'required',
            'to_user_id' => 'required|integer|min:1',       // 要转移的客服
            'source_user_id' => 'required|integer|min:1',   // 订单原客服
        ];
        $validate = Validator::make($request, $rules);
        if ($validate->fails()) {
            $error_list = $validate->errors()->all();
            $responseDto->status = ErrorMsg::PARAM_ERROR;
            $responseDto->msg = implode("\r\n", $error_list);
            return $responseDto;
        }
        // 必须是售前部门 TODO
        /*if (!$this->departmentRepository) $this->departmentRepository = new DepartmentRepository();
        $department_info = $this->departmentRepository->getInfoById($login_user_info['department_id']);
        if (1 != $department_info['job_type']) {
            // 只有售前部门才可以
            ErrorMsg::FillResponseAndLog($responseDto, ErrorMsg::CUSTOMER_TRANSFER_ONLY_BY_AFTER_SALE);
            return $responseDto;
        }*/

        // 检查两个客服id是否存在
        $users_info = $this->userRepository->getUsersByIds([$request['source_user_id'], $request['to_user_id']]);
        // 来源用户不存在
        if (!$users_info || !isset($users_info[$request['source_user_id']])) {
            ErrorMsg::FillResponseAndLog($responseDto, ErrorMsg::USER_SOURCE_NOT_EXISTS);
            return $responseDto;
        }
        if (!isset($users_info[$request['to_user_id']])) {
            ErrorMsg::FillResponseAndLog($responseDto, ErrorMsg::USER_TO_NOT_EXISTS);
            return $responseDto;
        }

        // 获取订单信息，检查订单的原客服是否匹配
        //$order_list = $this->theRepository->getOrderInfosByOrderIds($request['order_ids']);
        //foreach ($order_list as $order_inf) {}
        // 更新, 直接写sql，将原客服id换成新客服id，where条件要写好
        $sql_where = [
            'id' => ['in', $request['order_ids']],
            'pre_sale_id' => $request['source_user_id'],
        ];
        $data_arr = [
            'pre_sale_id' => $request['to_user_id']
        ];
        // 事物处理
        \DB::beginTransaction();
        try {
            $this->theRepository->updateMultiByCondition($sql_where, $data_arr);
            \DB::commit();
        } catch (\Exception $e) {
            $msg = 'update error: ' . $e->getMessage() . ' data:';
            \Log::error($msg, $request);
            \DB::rollBack();
            ErrorMsg::FillResponseAndLog($responseDto, ErrorMsg::UPDATE_DB_FAILED);
            return $responseDto;
        }
        return $responseDto;
    }
}
