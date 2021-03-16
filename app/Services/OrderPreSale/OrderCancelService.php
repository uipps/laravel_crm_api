<?php

namespace App\Services\OrderPreSale;

use App\Dto\OrderDetailDto;
use App\Dto\OrderDto;
use App\Dto\OrderStatsPreSaleDto;
use App\Libs\Utils\ErrorMsg;
use App\Dto\DataListDto;
use App\Dto\ResponseDto;
use App\Dto\OrderCancelDto;
use App\Models\Admin\Department;
use App\Models\Admin\User;
use App\Repositories\OrderPreSale\OrderCancelRepository;
use App\Repositories\Admin\UserRepository;
use App\Repositories\OrderPreSale\OrderDetailRepository;
use App\Repositories\OrderPreSale\OrderRepository;
use App\Services\BaseService;
use Illuminate\Support\Facades\Validator;


class OrderCancelService extends BaseService
{
    protected $theRepository;
    protected $departmentRepository;
    protected $orderRepository;
    protected $orderDetailRepository; // 就是商品信息

    public function __construct() {
        $this->theRepository = new OrderCancelRepository();
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
        // 订单状态的限制，这里只显示售前订单
        $request['job_type'] = Department::JOB_TYPE_PRE_SALE;
        if (!isset($request['status'])) $request['status'] = ['!=', 2]; // 默认未归档

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
                // 员工只能看到自己的，order表中的客服字段
                if (!isset($request['pre_sale_id'])) $request['pre_sale_id'] = $login_user_info['id'];
            }
        }

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
        $order_id = isset($v_detail['order_id']) ? $v_detail['order_id'] : $v_detail['id'];
        // 字段映射，数据表中只有status字段，无status_cancel字段，但是前端需要status_cancel字段；DTO修改没有作用，因为进行数组合并
        if (isset($v_detail['status']))
            $v_detail['status_cancel'] = $v_detail['status'];
        // 前端跳转到详情还是修改页面
        if (isset($v_detail['opt_result']) && 0 == $v_detail['opt_result']) {
            $v_detail['submit_type'] = 2;
        }

        // DTO合并方法一，验证可行
        $v_info = array_merge((array)(new OrderCancelDto()), (array)(new OrderDto()));
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

    public function addOrUpdate($request = null) {
        if (!$request) $request = request()->all();
        $responseDto = new ResponseDto();

        if ('cli' != php_sapi_name()) $current_uid = auth('api')->id();
        else $current_uid = ($request['creator_id'] ?? 0) + 0;
        // 参数校验数组, 当前登录用户是否有权限暂不验证，后面统一处理
        $rules = [
            'order_id' => 'required|integer|min:1',
            'remark' => 'required|string',
            'submit_type' => 'sometimes|integer|min:1',
        ];
        $validate = Validator::make($request, $rules);
        if ($validate->fails()) {
            $error_list = $validate->errors()->all();
            $responseDto->status = ErrorMsg::PARAM_ERROR;
            $responseDto->msg = implode("\r\n", $error_list);
            return $responseDto;
        }
        $curr_datetime = date('Y-m-d H:i:s');
        $data_arr = $request;

        // 检查该记录是否存在
        $order_detail = $this->orderRepository->getInfoById($request['order_id']);
        if (!$order_detail) {
            ErrorMsg::FillResponseAndLog($responseDto, ErrorMsg::ORDER_DATA_NOT_EXISTS);
            return $responseDto;
        }

        // 取消订单申请 列表中是否已存在
        $v_detail = $this->theRepository->getInfoById($request['order_id']);
        if ($v_detail) {
            // 更新
            $data_arr['updator_id'] = $current_uid;
            $data_arr['updated_time'] = $curr_datetime;

            $rlt = $this->theRepository->updateData($v_detail['id'], $data_arr);
            if (!$rlt) {
                ErrorMsg::FillResponseAndLog($responseDto, ErrorMsg::UPDATE_DB_FAILED);
                return $responseDto;
            }
        } else {
            $data_arr['creator_id'] = $current_uid;
            $data_arr['updator_id'] = $data_arr['creator_id'];
            $data_arr['created_time'] = $curr_datetime;
            $data_arr['updated_time'] = $curr_datetime;
            $data_arr['order_no'] = $order_detail['order_no'];

            $v_id = $this->theRepository->insertGetId($data_arr);
            if (!$v_id) {
                ErrorMsg::FillResponseAndLog($responseDto, ErrorMsg::INSERT_DB_FAILED);
                return $responseDto;
            }
            // 暂不返回详情，前端跳列表页
            $responseDto->data = ['id'=>$v_id];
        }
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

        $v_detail = $this->orderRepository->getInfoById($request[$field_id]);
        if (!$v_detail) {
            ErrorMsg::FillResponseAndLog($responseDto, ErrorMsg::DATA_EMPTY);
            return $responseDto;
        }
        // 通过 order_id，获取对应的订单详情数据
        //$order_info_list = $this->orderRepository->getOrderInfosByOrderIds([$id]); // 批量获取
        $order_info_list[$id] = $v_detail;
        // 通过 order_id，获取订单对应的商品详情数据
        $goods_info_list = $this->orderDetailRepository->getGoodsListByOrderIds([$id]); // 三维数组

        // 成功，返回信息
        $v_info = self::getOneOrderInfo($v_detail, $order_info_list, $goods_info_list);
        $responseDto->data = $v_info;

        return $responseDto;
    }

    public function delete($id) {
        // 进行软删除，更新状态即可
        $request['status'] = '-1';
        $request['order_id'] = $id;
        return self::addOrUpdate($request);
    }

    // 更新单条，取消订单申请，只能添加“申请备注”
    public function updateOne($id) {
        $request = request()->all();
        $request['order_id'] = $id;
        $responseDto = new ResponseDto();

        // 参数校验数组, 当前登录用户是否有权限暂不验证，后面统一处理
        $rules = [
            'order_id' => 'required|integer|min:1',
            'remark' => 'required|string',
            'submit_type' => 'sometimes|integer|min:1',
        ];
        $validate = Validator::make($request, $rules);
        if ($validate->fails()) {
            $error_list = $validate->errors()->all();
            $responseDto->status = ErrorMsg::PARAM_ERROR;
            $responseDto->msg = implode("\r\n", $error_list);
            return $responseDto;
        }
        // 无保存，只有1提交；2-保存
        $data_arr = [
            'order_id' => $request['order_id'],
            'remark' => $request['remark'],
        ];

        return self::addOrUpdate($data_arr);
    }

    // 取消订单申请 - 待处理列表
    public function getCancelOrderNoDealwith() {
        $request = request()->all();
        $request['status'] = 0;             // 状态 0未提交1已提交 2已归档
        $request['opt_result'] = 0;         // 处理结果 0未处理1成功-1失败
        return self::getList($request);
    }

    // 取消订单申请 - 处理成功
    public function getAskForCancelDealwithSucc() {
        $request = request()->all();
        $request['status'] = 1;             // 状态 0未提交1已提交 2已归档
        $request['opt_result'] = 1;         // 处理结果 0未处理1成功-1失败
        return self::getList($request);
    }

    // 取消订单申请 - 处理失败
    public function getAskForCancelDealwithFail() {
        $request = request()->all();
        $request['status'] = 1;             // 状态 0未提交1已提交 2已归档
        $request['opt_result'] = -1;        // 处理结果 0未处理1成功-1失败
        return self::getList($request);
    }

    // 取消订单申请 - 归档
    public function getAskForCancelFinish() {
        $request = request()->all();
        $request['status'] = 2;             // 状态 0未提交1已提交 2已归档
        $request['opt_result'] = ['!=', 0]; // 处理结果 0未处理1成功-1失败
        return self::getList($request);
    }

    // 归档操作
    public function placeOnOrder() {
        $request = request()->all();
        $responseDto = new ResponseDto();

        // 参数校验数组, 当前登录用户是否有权限暂不验证，后面统一处理
        $rules = [
            'order_ids' => 'required'
        ];
        $validate = Validator::make($request, $rules);
        if ($validate->fails()) {
            $error_list = $validate->errors()->all();
            $responseDto->status = ErrorMsg::PARAM_ERROR;
            $responseDto->msg = implode("\r\n", $error_list);
            return $responseDto;
        }

        $login_user_info = self::getCurrentLoginUserInfo();

        // 通过 order_id 列表，获取对应的订单详情数据
        //$order_info_list = $this->orderRepository->getOrderInfosByOrderIds($request['order_ids']); // 批量获取
        // 通过 order_id 列表，获取对应的取消订单申请详情数据
        $cancel_info_list = $this->theRepository->getCancelOrderInfosByOrderIds($request['order_ids']);

        // 订单必须在order_cancel表中，opt_result不能为0，status不能是1；
        $update_ids = [];
        foreach ($cancel_info_list as $row) {
            if (0 == $row['opt_result'] || 1 == $row['status']) {
                continue;
            }
            $update_ids[] = $row['id'];
        }

        $update_data_arr = [
            'status' => 1
        ];

        if (!$update_ids) {
            // 未找到符合条件的
            return $responseDto;
        }

        $rlt = $this->theRepository->updateDataByIds($update_ids, $update_data_arr);
        if (!$rlt) {
            ErrorMsg::FillResponseAndLog($responseDto, ErrorMsg::UPDATE_DB_FAILED);
            return $responseDto;
        }

        // 删除统计缓存
        $this->orderRepository->deleteCacheByUidAndType($login_user_info['id'], 'askforcancel');

        return $responseDto;
    }

    // 选择源订单，广告单、手工单等都可以添加取消；无效单，就不用取消了吧
    public function getOptionalOrder() {
        $request = request()->all(); // 参数接收
        $responseDto = new ResponseDto();

        $login_user_info = self::getCurrentLoginUserInfo();
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
        // 订单状态的限制，这里只显示售前订单，无效单不显示
        $request['order_type'] = ['in', [1,2]];
        //$request['department_id'] = $login_user_info['department_id']; 只能是本部门的订单 TODO
        $request['invalid_status'] = 0; // 有效单

        // 已经申请取消的，不再重复申请 TODO 将来数据会不会很大
        $cancel_orders = $this->theRepository->getAllCancelOrderIds();
        if ($cancel_orders) {
            $request['id'] = ['notin', $cancel_orders]; // 已经提交过的
        }

        // 直接获取数据，包含总数字段
        $list = $this->orderRepository->getList($request);
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
            // 通过 order_id，获取对应的订单详情数据
            //$order_info_list = $this->orderRepository->getOrderInfosByOrderIds($order_ids); // 批量获取
            $order_info_list = array_column($list[$responseDto::DTO_FIELD_LIST], null, 'id');

            // 通过 order_id，获取订单对应的商品详情数据
            $goods_info_list = $this->orderDetailRepository->getGoodsListByOrderIds($order_ids); // 三维数组

            // 成功，返回列表信息
            foreach ($list[$responseDto::DTO_FIELD_LIST] as $key => $v_detail) {
                $list[$responseDto::DTO_FIELD_LIST][$key] = self::getOneOrderInfo($v_detail, $order_info_list, $goods_info_list);
            }
        }
        $data_list = new DataListDto();
        $data_list->Assign($list);
        $responseDto->data = $data_list;

        return $responseDto;
    }
}
