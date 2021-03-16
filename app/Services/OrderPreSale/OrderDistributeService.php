<?php

namespace App\Services\OrderPreSale;

use App\Dto\OrderStatsPreSaleDto;
use App\Libs\Utils\ErrorMsg;
use App\Dto\DataListDto;
use App\Dto\ResponseDto;
use App\Dto\OrderDistributeDto;
use App\Models\Admin\User;
use App\Repositories\OrderPreSale\OrderAuditRepository;
use App\Repositories\OrderPreSale\OrderDistributeRepository;
use App\Repositories\Admin\UserRepository;
use App\Services\BaseService;
use Illuminate\Support\Facades\Validator;
use App\Repositories\OrderPreSale\OrderRepository;
use App\Repositories\OrderPreSale\OrderDetailRepository;
use App\Dto\OrderDetailDto;
use App\Dto\OrderDto;
use App\Models\OrderPreSale\OrderAudit;
use App\Models\OrderPreSale\OrderRepeat;

class OrderDistributeService extends BaseService
{
    protected $theRepository;
    protected $departmentRepository;
    protected $orderRepository;
    protected $orderAuditRepository;
    protected $orderDetailRepository; // 就是商品信息

    public function __construct() {
        $this->theRepository = new OrderDistributeRepository();
        $this->orderRepository = new OrderRepository();
        $this->orderDetailRepository = new OrderDetailRepository();
        $this->userRepository = new UserRepository(); // 用于权限检查
    }

    public function getList($request=null) {
        if (!$request) $request = request()->all(); // 参数接收
        $responseDto = new ResponseDto();

        //$login_user_info = self::getCurrentLoginUserInfo(); // TODO 当前登录用户是否有权限，统一一个方法放到BaseService中

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
                // 主管能看到本部门的全部列表，但是分配，只能看分配给下级的
                $child_dept_list[] = $login_user_info['department_id'];
                //if (!isset($request['distributed_dep_id'])) $request['distributed_dep_id'] = ['in', parent::getChildrenDepartmentByDeptId($login_user_info['department_id'])];
                if (!isset($request['distributed_dep_id'])) $request['distributed_dep_id'] = $login_user_info['department_id'];
            } else if (User::LEVEL_STAFF == $login_user_info['level']) {
                // 员工只能看到自己的
                if (!isset($request['distributed_user_id'])) $request['distributed_user_id'] = $login_user_info['id'];
            }
        }
        $request['job_type'] = 1;   // 只显示售前

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
            $v_detail = $this->theRepository->getInfoByOrderId($request['order_id']);
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

        if (isset($request['order_id']) && $request['order_id']) {
            // 更新
            $rlt = $this->theRepository->updateData($request['order_id'], $data_arr);
            if (!$rlt) {
                ErrorMsg::FillResponseAndLog($responseDto, ErrorMsg::UPDATE_DB_FAILED);
                return $responseDto;
            }
        } else {
            $v_id = 0; // $this->theRepository->insertGetId($data_arr);
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

    // 更新单条
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

    // 未分配订单列表
    public function distributeNotList() {
        $request = request()->all();

        // 不同身份的看到的会不一样，依据当前登录用户的身份进行查询
        $login_user_info = self::getCurrentLoginUserInfo();
        if (User::LEVEL_STAFF == $login_user_info['level']) {
            // 员工看不到，只有主管可以查看
            $responseDto = new ResponseDto();
            ErrorMsg::FillResponseAndLog($responseDto, ErrorMsg::NO_PRIVILEGE);
            return $responseDto;
        }

        //if ($login_user_info['department_id'] > 0) {
        //    $request['distributed_dep_id'] = $login_user_info['department_id'];  // order_distribute 表
        //}

        $request['job_type'] = 1;               // order_distribute 表 岗位类别 1售前2售后
        //$request['status'] = 1;                 // order_distribute 表 状态 0无效 1有效
        $request['distribute_status'] = 0;      // order/order_distribute 两表共有 分配状态 0未分配1已分配-1已撤销
        $request['order_type'] = 1;             // order表, 订单类型 1广告2售前手工3售后手工
        return self::getList($request);
    }

    // 已分配订单列表
    public function distributedList() {
        $request = request()->all();

        // 不同身份的看到的会不一样，依据当前登录用户的身份进行查询
        $login_user_info = self::getCurrentLoginUserInfo();
        if (User::LEVEL_STAFF == $login_user_info['level']) {
            // 员工看不到，只有主管可以查看，员工身份，只能看到已审核、未审核（已处理、未处理）
            $responseDto = new ResponseDto();
            ErrorMsg::FillResponseAndLog($responseDto, ErrorMsg::NO_PRIVILEGE);
            return $responseDto;
        }

        //if ($login_user_info['department_id'] > 0) {
        //    $request['distributed_dep_id'] = $login_user_info['department_id'];  // order_distribute 表
        //}

        $request['job_type'] = 1;               // order_distribute 表 岗位类别 1售前2售后
        //$request['status'] = 1;                 // order_distribute 表 状态 0无效 1有效
        $request['distribute_status'] = 1;      // order/order_distribute 两表共有 分配状态 0未分配1已分配-1已撤销
        $request['order_type'] = 1;             // order表, 订单类型 1广告2售前手工3售后手工
        return self::getList($request);
    }

    private function getOneOrderInfo($v_detail, $order_info_list, $goods_info_list) {
        $order_id = $v_detail['order_id'];

        // DTO合并方法一，验证可行
        $v_info = array_merge((array)(new OrderDistributeDto()), (array)(new OrderDto()));
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

    // 订单分配
    public function distributeOrder() {
        $request = request()->all();
        $responseDto = new ResponseDto();

        $curr_datetime = date('Y-m-d H:i:s');
        if ('cli' != php_sapi_name()) $current_uid = auth('api')->id();
        else $current_uid = ($request['creator_id'] ?? 0) + 0;
        // 参数校验数组, 当前登录用户是否有权限暂不验证，后面统一处理
        $rules = [
            'pre_sale_id' => 'required|integer|min:1',
            'ids' => 'required',                            // order_distribute 表中的自增ID，不是order_id列表
            //'language_id' => 'sometimes|integer|min:1',
        ];
        if (isset($request['revoke_distribute']) && $request['revoke_distribute'])
            unset($rules['pre_sale_id']);

        $validate = Validator::make($request, $rules);
        if ($validate->fails()) {
            $error_list = $validate->errors()->all();
            $responseDto->status = ErrorMsg::PARAM_ERROR;
            $responseDto->msg = implode("\r\n", $error_list);
            return $responseDto;
        }
        if (!is_array($request['ids'])) {
            ErrorMsg::FillResponseAndLog($responseDto, ErrorMsg::PARAM_ERROR);
            return $responseDto;
        }
        // 当前登录者必须是主管(超级管理员不能分单)
        $login_user_info = self::getCurrentLoginUserInfo();
        if (!$login_user_info || 1 == $login_user_info['role_id'] || User::LEVEL_ADMIN != $login_user_info['level']) {
            // 只有主管才可以
            ErrorMsg::FillResponseAndLog($responseDto, ErrorMsg::ORDER_OPT_NEED_ADMIN);
            return $responseDto;
        }

        // 撤销不需要客服
        if (!isset($request['revoke_distribute']) || !$request['revoke_distribute']) {
            // 分配的情况，检查客服是否存在的
            $pre_detail = $this->userRepository->getUserById($request['pre_sale_id']);
            if (!$pre_detail || !isset($pre_detail['level'])) {
                ErrorMsg::FillResponseAndLog($responseDto, ErrorMsg::USER_NOT_EXISTS);
                return $responseDto;
            }
            if (0 == $pre_detail['status']) {
                ErrorMsg::FillResponseAndLog($responseDto, ErrorMsg::USER_STATUS_CLOSE);
                return $responseDto;
            }

            // 主管和员工部门必须一致
            if ($pre_detail['department_id'] != $login_user_info['department_id']) {
                ErrorMsg::FillResponseAndLog($responseDto, ErrorMsg::ADMIN_USER_DEPARTMENT_NOT_MATCH);
                return $responseDto;
            }
        }
        // TODO 手动分单，一级一级分配，是否允许跨级分单
//        if (User::LEVEL_STAFF != $pre_detail['level']) {
//            ErrorMsg::FillResponseAndLog($responseDto, ErrorMsg::ORDER_DISTRIBUTE_ONLY_TO_STAFF);
//            return $responseDto;
//        }
        // 检查订单是否未分配，分配过的是否还能再分配？ TODO
        // 订单是否存在？从列表选择的，暂时不用检查 TODO 多主管抢单的情况怎么处理？暂时先走正常流程，有时间再仔细处理
        // 获取选中的订单信息，查询订单相关信息

        $order_distribute_list = $this->theRepository->getInfoByIds($request['ids'], 'order_id');
        $order_ids = array_column($order_distribute_list, 'order_id');


        $order_info_list = $this->orderRepository->getOrderInfosByOrderIds($order_ids);
        if (!$order_info_list) {
            ErrorMsg::FillResponseAndLog($responseDto, ErrorMsg::DATA_NOT_EXISTS);
            return $responseDto;
        }

        // 校验订单

        // 数据增加几个默认值
        $data_arr = $request;
        $data_arr['updator_id'] = $current_uid;
        $data_arr['updated_time'] = $curr_datetime;

        // 依据参数进行分配还是撤销
        if (isset($request['revoke_distribute']) && $request['revoke_distribute']) {
            $rlt = $this->revokeDistribute($data_arr, $order_info_list, $curr_datetime, $current_uid);
            if (!$rlt) {
                ErrorMsg::FillResponseAndLog($responseDto, ErrorMsg::UPDATE_DB_FAILED);
                return $responseDto;
            }
            return $responseDto;
        }

        // 订单分配逻辑
        $rlt = $this->updateOrderAndDistributeData($data_arr, $pre_detail, $order_info_list, $curr_datetime, $current_uid);
        if (!$rlt) {
            ErrorMsg::FillResponseAndLog($responseDto, ErrorMsg::UPDATE_DB_FAILED);
            return $responseDto;
        }
        return $responseDto;
    }

    // 分配订单: 更新distribute表(员工么有下级，无需插入一条分配记录), 订单总表（分配状态维护一下），插入audit(未审核)，需要进行事务处理
    public function updateOrderAndDistributeData($data_arr, $pre_detail, $order_info_list, $create_time, $creator_id) {
        if (!$this->orderAuditRepository) $this->orderAuditRepository = new OrderAuditRepository();

        \DB::beginTransaction();
        try {
            // distribute表直接更新，audit插入一条，订单总表仅仅维护一下状态即可 TODO 检查是否已经存在该记录
            //$this->orderAuditRepository->insertMultipleAuditByPreSaleId($pre_detail, $order_info_list, $create_time, $creator_id);
            foreach ($order_info_list as $l_order_info) {
                if (1 == $l_order_info['distribute_status']) {
                    // 已经分配的不用再分配, 自动分单的不能操作
                    continue;
                }
                // 检查设置，是否设置为自动，自动不能执行手动分单；1 == $l_order_info['distribute_type']
                $distribute_info = $this->theRepository->getInfoByDeptIdOrderId($l_order_info['id'], $pre_detail['department_id']);
                //if (!$distribute_info || 1 == $distribute_info['distribute_type']) {
                    // 如果数据不存在，或者自动分单的不能进行人工分单
                    // continue;
                //}

                $repeatFlag = 0;
                $repeat = OrderRepeat::where('order_id', $l_order_info['id'])->first();
                if($repeat) $repeatFlag = 1;

                // audit表，不存在需要插入
                $audit_info = $this->orderAuditRepository->getInfoByDeptIdOrderId($l_order_info['id'], $pre_detail['department_id']);

                if (!$audit_info) {
                    $this->orderAuditRepository->insertAuditByPreSaleId($pre_detail, $l_order_info, $create_time, $creator_id, $repeatFlag);
                }else{
                    OrderAudit::where('order_id', $l_order_info['id'])->update(['repeat_flag' => $repeatFlag]);
                }

                


                $row = [];
                $row['updator_id'] = $creator_id;
                $row['updated_time'] = $create_time;

                // distribute表
                $row['distribute_user_id'] = $creator_id;       // 分配人id
                $row['distribute_status'] = 1;                  // 两表共用
                $row['distributed_dep_id'] = $pre_detail['department_id'];
                $row['distributed_user_id'] = $pre_detail['id'];
                $row['distribute_type'] = 0;   // 手动
                $row['repeat_flag'] = $repeatFlag;   // 手动
                // order 表
                $row['distributed_time'] = $create_time;   // 分配时间
                $row['distribute_time'] = $create_time;   // 分配时间
                $row['pre_sale_id'] = $pre_detail['id'];

                $this->orderRepository->updateData($l_order_info['id'], $row);
                $this->theRepository->updateData($distribute_info['id'], $row);
            }
            \DB::commit();
        } catch (\Exception $e) {
            $msg = 'db-Transaction-error: table error: ' . $e->getMessage() . ' data:';
            \Log::error($msg, $data_arr);
            \DB::rollBack();
            return false;
        }

        // 删除统计缓存
        $this->orderRepository->deleteCacheByUidAndType($creator_id, 'distribute');

        return true;
    }

    // 撤销分单
    public function revokeDistribute($data_arr, $order_info_list, $create_time, $creator_id) {
        if (!$this->orderAuditRepository) $this->orderAuditRepository = new OrderAuditRepository();

        \DB::beginTransaction();
        try {
            foreach ($order_info_list as $l_order_info) {
                // 已经审核或其他处理的不能撤销
                if ($l_order_info['pre_opt_type'] > 0 || $l_order_info['audit_status'] > 0) {
                    continue;
                }
                if (0 == $l_order_info['distribute_status']) {
                    // 已经撤销的不能再撤销
                    continue;
                }
                // 检查设置，是否设置为自动，自动不能执行手动分单；1 == $l_order_info['distribute_type']
                $distribute_info = $this->theRepository->getInfoByDeptIdOrderId($l_order_info['id'], $l_order_info['department_id']);
                //if (!$distribute_info || 1 == $distribute_info['distribute_type']) {
                    // 如果数据不存在，或者自动分单的不能进行人工分单
                    // continue;
                //}

                // TODO 撤销只能撤销自己分配的

                // audit表，存在需要删除
                $audit_info = $this->orderAuditRepository->getInfoByDeptIdOrderId($l_order_info['id'], $l_order_info['department_id']);
                if ($audit_info && isset($audit_info['id'])) {
                    $this->orderAuditRepository->delete($audit_info['id']);
                }

                $row = [];
                $row['updator_id'] = $creator_id;
                $row['updated_time'] = $create_time;

                // distribute表
                //$row['distribute_user_id'] = $creator_id;       // 分配人id
                $row['distribute_status'] = 0;          // 两表共用
                $row['distributed_dep_id'] = 0;
                $row['distributed_user_id'] = 0;
                $row['distribute_type'] = 0;            // 手动
                $row['repeat_flag'] = 0;
                // order 表
                $row['distribute_time'] = $this->theRepository::DATETIME_NOT_NULL_DEFAULT;   // 分配恢复

                $this->orderRepository->updateData($l_order_info['id'], $row);
                $this->theRepository->updateData($distribute_info['id'], $row);
            }
            \DB::commit();
        } catch (\Exception $e) {
            $msg = 'db-Transaction-error: table error: ' . $e->getMessage() . ' data:';
            \Log::error($msg, $data_arr);
            \DB::rollBack();
            return false;
        }

        // 删除统计缓存
        $this->orderRepository->deleteCacheByUidAndType($creator_id, 'distribute');

        return true;
    }

}
