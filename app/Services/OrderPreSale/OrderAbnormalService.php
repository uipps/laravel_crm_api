<?php

namespace App\Services\OrderPreSale;

use App\Dto\OrderStatsPreSaleDto;
use App\Libs\Utils\ErrorMsg;
use App\Dto\DataListDto;
use App\Dto\ResponseDto;
use App\Dto\OrderAbnormalDto;
use App\Models\Admin\Department;
use App\Models\Admin\User;
use App\Repositories\OrderPreSale\OrderAbnormalRepository;
use App\Repositories\Admin\UserRepository;
use App\Repositories\OrderPreSale\OrderOptRecordRepository;
use App\Services\BaseService;
use Illuminate\Support\Facades\Validator;
use App\Repositories\OrderPreSale\OrderRepository;
use App\Repositories\OrderPreSale\OrderDetailRepository;
use App\Dto\OrderDetailDto;
use App\Dto\OrderDto;
use App\Facades\Thrift;
use App\Models\OrderPreSale\Order;

class OrderAbnormalService extends BaseService
{
    protected $theRepository;
    protected $departmentRepository;
    protected $orderRepository;
    protected $orderDetailRepository; // 就是商品信息

    public function __construct() {
        $this->theRepository = new OrderAbnormalRepository();
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

    public function addOrUpdate($request = null) {
        if (!$request) $request = request()->all();
        $responseDto = new ResponseDto();

        if ('cli' != php_sapi_name()) $current_uid = auth('api')->id();
        else $current_uid = ($request['creator_id'] ?? 0) + 0;
        // 参数校验数组, 当前登录用户是否有权限暂不验证，后面统一处理
        //$field_id = 'id';
        $rules = [
            'order_id' => 'sometimes|integer',
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
        if (isset($request['order_id']) && $request['order_id']) {
            // 修改的情况
            $data_arr['order_id'] = $request['order_id'];
            // 检查该记录是否存在
            $v_detail = $this->theRepository->getInfoById($request['order_id']);
            if (!$v_detail) {
                ErrorMsg::FillResponseAndLog($responseDto, ErrorMsg::DATA_NOT_EXISTS);
                return $responseDto;
            }
            if (1 == $v_detail['status']) {
                // 已处理的就不能再处理了
                ErrorMsg::FillResponseAndLog($responseDto, ErrorMsg::ORDER_HAS_DEALWITHED);
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

        if (isset($request['order_id']) && $request['order_id']) {
            // 更新
            $rlt = $this->updateOrderAndAbnormalData($request['order_id'], $data_arr);
            if (!$rlt) {
                ErrorMsg::FillResponseAndLog($responseDto, ErrorMsg::UPDATE_DB_FAILED);
                return $responseDto;
            }
        } else {
            //$v_id = $this->insertOrderAndAbnormalData($data_arr);
            $v_id = 0;
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

        $v_detail = $this->theRepository->getInfoById($request[$field_id]);
        if (!$v_detail) {
            ErrorMsg::FillResponseAndLog($responseDto, ErrorMsg::DATA_EMPTY);
            return $responseDto;
        }
        // 通过 order_id，获取对应的订单详情数据
        $order_info_list = $this->orderRepository->getOrderInfosByOrderIds([$id]); // 批量获取
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

    // 更新单条，异常单，只能添加“异常备注”
    public function updateOne($id) {
        $request = request()->all();
        $request['order_id'] = $id;
        $responseDto = new ResponseDto();

        // 参数校验数组, 当前登录用户是否有权限暂不验证，后面统一处理
        $rules = [
            'order_id' => 'required|integer|min:1',
            'abnormal_remark' => 'required|string',
            'submit_type' => 'required|integer|min:1',
        ];
        $validate = Validator::make($request, $rules);
        if ($validate->fails()) {
            $error_list = $validate->errors()->all();
            $responseDto->status = ErrorMsg::PARAM_ERROR;
            $responseDto->msg = implode("\r\n", $error_list);
            return $responseDto;
        }
        // 1-提交；2-保存
        if (1 == $request['submit_type']) {
            // 去掉其他字段，只留下这2个字段数据
            $data_arr = [
                'order_id' => $request['order_id'],
                'abnormal_remark' => $request['abnormal_remark'],
            ];
            // 需要更新的状态
            $data_arr['status'] = 1;    // 状态 0未处理1已处理
            $data_arr['job_type'] = 1;  // 岗位类别 1售前2售后

            // order表字段
            $data_arr['pre_opt_type'] = 4;      // 售前处理结果 对应订单处理类别id,最近一次 TODO 异常备注？
            //$data_arr['pre_opt_remark'] = $request['abnormal_remark'];    // 售前处理备注 最近一次
            $data_arr['pre_opt_time'] = date('Y-m-d H:i:s'); // 售前处理时间 最近一次
            //$data_arr['audit_status'] = date('Y-m-d H:i:s');  // TODO 审核状态 0未审核1已审核
            //$data_arr['audit_time'] = date('Y-m-d H:i:s');    // 审核时间 最近一次
        } else if (2 == $request['submit_type']) { // 保存
            $data_arr = [
                'order_id' => $request['order_id'],
                'abnormal_remark' => $request['abnormal_remark'],
            ];
            // 不需要更新状态
            //$data_arr['status'] = 0;    // 状态 0未处理1已处理
            //$data_arr['job_type'] = 1;  // 岗位类别 1售前2售后

            // order表也更新一下
            $data_arr['pre_opt_type'] = 5;      // 售前处理结果 对应订单处理类别id,最近一次 TODO 异常订单备注保存
            //$data_arr['pre_opt_remark'] = $request['abnormal_remark'];    // 售前处理备注 最近一次
            $data_arr['pre_opt_time'] = date('Y-m-d H:i:s'); // 售前处理时间 最近一次
        } else {
            // 无取消
            ErrorMsg::FillResponseAndLog($responseDto, ErrorMsg::UNSUPPORTED_TYPE);
            return $responseDto;
        }
        return self::addOrUpdate($data_arr);
    }

    // 异常订单物流信息
    public function shipping($id) {
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

        $order = Order::findOrFail($id);
        // dd($order->order_no);
        $traceInfo = Thrift::getOrderTrace($order->order_no);
        $data = [
            'order' => $order,
            'message' => $traceInfo['message'],
            'trace_list' => $traceInfo['list']
        ];
        // 成功，返回信息
        //$v_info = new OrderAbnormalDto();
        //$responseDto->data = $v_info;

        $responseDto->data = $data;

        return $responseDto;
    }

    // 插入订单总表order、商品表order_detail和该类型表（异常订单），需要进行事务处理
    /*public function insertOrderAndAbnormalData($data_arr, $request, $create_time='', $creator_id=0) {
        \DB::beginTransaction();
        try {
            $order_id = $this->orderRepository->insertGetId($data_arr);
            if (!$order_id)
                return false;
            $data_arr['order_id'] = $order_id;
            if (!isset($data_arr['remark'])) $data_arr['remark'] = ''; // 该字段不能为空 ？？ TEXT类型？？
            $this->theRepository->insertGetId($data_arr);
            // 商品信息，还需要插入 order_detail 表
            $this->orderDetailRepository->insertMultipleByOrderId($order_id, $data_arr['goods_info']);
            \DB::commit();
        } catch (\Exception $e) {
            $msg = 'db-Transaction-error: table order ' . ' error: ' . $e->getMessage() . ' data:';
            \Log::error($msg, $request);
            \DB::rollBack();
            return false;
        }
        return $order_id;
    }*/

    // 只更新备注，顺便修改一些状态，涉及本表和订单总表order，商品信息不需要处理，需要进行事务处理
    public function updateOrderAndAbnormalData($order_id, $data_arr) {
        \DB::beginTransaction();
        try {
            $this->orderRepository->updateData($order_id, $data_arr);
            $this->theRepository->updateData($order_id, $data_arr);
            \DB::commit();
        } catch (\Exception $e) {
            $msg = 'db-Transaction-error: table order error: ' . $e->getMessage() . ' data:';
            \Log::error($msg, $data_arr);
            \DB::rollBack();
            return false;
        }

        // 记录操作日志, 保存和提交都记录一下
        if (isset($data_arr['pre_opt_type'])) {
            $data_arr['opt_type_id'] = $data_arr['pre_opt_type'];
            $data_arr['optator_id'] = $data_arr['updator_id'] ?? 0;
            $data_arr['remark'] = $data_arr['abnormal_remark'] ?? '';
            (new OrderOptRecordRepository())->insertOrderOptRecord($order_id, $data_arr);
        }

        return true;
    }

    private function getOneOrderInfo($v_detail, $order_info_list, $goods_info_list) {
        $order_id = $v_detail['order_id'];
        // 字段映射，数据表中只有status字段，无status_abnormal字段，但是前端需要status_abnormal字段；DTO修改没有作用，因为进行数组合并
        if (isset($v_detail['status'])) {
            $v_detail['status_abnormal'] = $v_detail['status'];
            if (0 == $v_detail['status']) {
                $v_detail['submit_type'] = 2;
            }
        }

        // DTO合并方法一，验证可行
        $v_info = array_merge((array)(new OrderabnormalDto()), (array)(new OrderDto()));
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

    // 异常订单 - 未处理列表
    public function getAbnormalNoDealwith() {
        $request = request()->all();
        $request['status'] = 0;
        return self::getList($request);
    }

    // 异常订单 - 已处理列表
    public function getAbnormalDealwith() {
        $request = request()->all();
        $request['status'] = 1;
        return self::getList($request);
    }
}
