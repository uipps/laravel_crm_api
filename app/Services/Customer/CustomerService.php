<?php

namespace App\Services\Customer;

use App\Dto\CustomerStatsDto;
use App\Libs\Utils\ErrorMsg;
use App\Dto\DataListDto;
use App\Dto\ResponseDto;
use App\Dto\CustomerDto;
use App\Models\Admin\User;
use App\Repositories\Admin\DepartmentRepository;
use App\Repositories\Customer\CustomerClueRepository;
use App\Repositories\Customer\CustomerRepository;
use App\Repositories\Admin\UserRepository;
use App\Services\BaseService;
use Illuminate\Support\Facades\Validator;


class CustomerService extends BaseService
{
    protected $theRepository;
    protected $customerClueRepository;
    protected $departmentRepository;

    public function __construct() {
        $this->theRepository = new CustomerRepository();
        $this->userRepository = new UserRepository(); // 用于权限检查
        $this->departmentRepository = new DepartmentRepository();
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

        // 依据登录者身份，拼装不同查询条件，超管可以看到所有
        $login_user_info = self::getCurrentLoginUserInfo();
        if (1 != $login_user_info['role_id']) {
            $department_info = $this->departmentRepository->getInfoById($login_user_info['department_id']);
            if (1 == $department_info['job_type']) {
                // 售前部门
                // 如果登录账号是员工，带上销售id：
                if (User::LEVEL_STAFF == $login_user_info['level'] && !isset($request['pre_sale_id'])) {
                    if (!isset($request['pre_sale_id'])) $request['pre_sale_id'] = $login_user_info['id'];
                }
                if (User::LEVEL_ADMIN == $login_user_info['level'] && !isset($request['pre_sale_id'])) {
                    // 当前账号所在部门的全部员工id，包括所有子部门
                    $user_ids = parent::getChildrenUserIdsByDeptId($login_user_info['department_id']);
                    if ($user_ids) {
                        if (!isset($request['pre_sale_id'])) $request['pre_sale_id'] = ['in', $user_ids];
                    } else {
                        $request['pre_sale_id'] = -1; // 确保筛查的数据为0 // TODO 减少查询，可优化
                    }
                }
            } else if (2 == $department_info['job_type']) {
                if (2 == $login_user_info['level']) {
                    // 员工，只看到分配给自己的
                    if (!isset($request['after_sale_id'])) $request['after_sale_id'] = $login_user_info['id'];
                } else {
                    // 主管
                    $staff_dept_list = self::getChildrenDepartmentByDeptId($login_user_info['department_id']);
                    //$request['department_id'] = ['in', $staff_dept_list]; // 线索需要使用，客户无此字段
                    $user_list = $this->userRepository->getUsersByDepartmentIds($staff_dept_list, 'id');
                    if ($user_list) {
                        $user_ids = array_column($user_list, 'id');
                        if (!isset($request['after_sale_id'])) $request['after_sale_id'] = ['in', $user_ids];    // 客户表只有此字段
                    } else {
                        $request['after_sale_id'] = -1; // 确保筛查的数据为0
                    }
                }
            } else {
                // 部门类型不存在
                ErrorMsg::FillResponseAndLog($responseDto, ErrorMsg::DEPARTMENT_JOB_TYPE_ERROR);
                return $responseDto;
            }
        }

        // 获取数据，包含总数字段
        $list = $this->theRepository->getList($request);
        if (!$list || !isset($list[$responseDto::DTO_FIELD_TOTOAL]) || !isset($list[$responseDto::DTO_FIELD_LIST])) {
            ErrorMsg::FillResponseAndLog($responseDto, ErrorMsg::DATA_EMPTY);
            return $responseDto;
        }
        if ($list[$responseDto::DTO_FIELD_LIST]) {
            //$customer_ids = array_column($list[$responseDto::DTO_FIELD_LIST], 'id');
            //$field = ['id', 'customer_id', 'facebook_id', 'whatsapp_id', 'line_id'];    // 只获取需要用到条数据
            //$customer_clue_list = $this->customerClueRepository->getListByCustomerIds($customer_ids, $field);

            // 成功，返回列表信息
            foreach ($list[$responseDto::DTO_FIELD_LIST] as $key => $v_detail) {
                $v_info = new CustomerDto();
                //if (isset($customer_clue_list[$v_detail['id']]))
                //    $v_info->Assign($customer_clue_list[$v_detail['id']]);  // facebook_id, whatsapp_id, line_id
                $v_info->Assign($this->addAllAttrName2Data($v_detail));
                $list[$responseDto::DTO_FIELD_LIST][$key] = $v_info;
            }
        }
        $data_list = new DataListDto();
        if (!isset($request[$responseDto::WITHOUT_ORDER_STATS]) || !$request[$responseDto::WITHOUT_ORDER_STATS]) {
            // 默认情况，所有客户列表都要带上订单统计数据；如果设置不需要携带统计数据则跳过
            $redis_stats_data = $this->theRepository->getCustomerNumStatsByUserId($login_user_info);
            $customer_stat = new CustomerStatsDto();
            $customer_stat->Assign($redis_stats_data); // 从redis缓存获取数据
            $data_list->meta[$responseDto::DTO_FIELD_NUMBER_STATS] = $customer_stat;
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
            $responseDto->data = ['id'=>$v_id];
        }
        return $responseDto;
    }

    public function detail($id) {
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

        $customer_ids = [$request[$field_id]];
        $field = ['id', 'customer_id', 'facebook_id', 'whatsapp_id', 'line_id'];    // 只获取需要用到条数据
        if (!$this->customerClueRepository) $this->customerClueRepository = new CustomerClueRepository();
        $customer_clue_list = $this->customerClueRepository->getListByCustomerIds($customer_ids, $field);

        // 成功，返回信息
        $v_info = new CustomerDto();
        if (isset($customer_clue_list[$v_detail['id']]))
            $v_info->Assign($customer_clue_list[$v_detail['id']]);  // facebook_id, whatsapp_id, line_id
        $v_info->Assign($this->addAllAttrName2Data($v_detail));
        $responseDto->data = $v_info;

        return $responseDto;
    }

    public function delete($id) {
        // 进行软删除，更新状态即可
        $request['status'] = '-1';
        $request['id'] = $id;
        return self::addOrUpdate($request);
    }

    // 更新单条
    public function updateOne($id) {
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
    }

    // 员工关联客户
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

        // 依据员工是售前还是售后，给定字段：
        $user_info = $this->userRepository->getUserById($request['user_id']);
        if (1 != $user_info['role_id']) {
            $department_info = $this->departmentRepository->getInfoById($user_info['department_id']);
            if (1 == $department_info['job_type']) {
                // 售前部门
                $request['pre_sale_id'] = $request['user_id'];  // 映射字段
            } else if (2 == $department_info['job_type']) {
                $request['after_sale_id'] = $request['user_id'];  // 映射字段
            }
        }
        unset($request['user_id']);

        return $this->getList($request);
    }

    // 售后主管，将客服服务过的客户转移给其他客服员工, 参考关联客户订单转移；也可能是管理员进行操作
    public function customerTransfer() {
        $request = request()->all();
        $responseDto = new ResponseDto();

        // 参数校验数组, 当前登录用户是否有权限暂不验证，后面统一处理
        $rules = [
            'customer_ids' => 'required',
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

        $login_user_info = self::getCurrentLoginUserInfo();
        // 如果是管理员，后续操作有点不一样
        if (1 == $login_user_info['role_id']) {
            return $this->adminCustomerTransfer();
        }

        if (!$login_user_info || User::LEVEL_ADMIN != $login_user_info['level']) {
            // 只有主管才可以
            ErrorMsg::FillResponseAndLog($responseDto, ErrorMsg::ORDER_OPT_NEED_ADMIN);
            return $responseDto;
        }
        // 必须是售后部门
        $department_info = $this->departmentRepository->getInfoById($login_user_info['department_id']);
        if (2 != $department_info['job_type']) {
            // 只有售后部门才可以
            ErrorMsg::FillResponseAndLog($responseDto, ErrorMsg::CUSTOMER_TRANSFER_ONLY_BY_AFTER_SALE);
            return $responseDto;
        }

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

        // 更新, 直接写sql，将原客服id换成新客服id，where条件要写好
        $rlt = $this->afterSaleCustomerTransferUpdate($request);
        if (!$rlt) {
            ErrorMsg::FillResponseAndLog($responseDto, ErrorMsg::UPDATE_DB_FAILED);
            return $responseDto;
        }
        return $responseDto;
    }

    // 更新售后
    private function afterSaleCustomerTransferUpdate($request) {
        // 更新, 直接写sql，将原客服id换成新客服id，where条件要写好
        $sql_where = [
            'id' => ['in', $request['customer_ids']],
            'after_sale_id' => $request['source_user_id'],
        ];
        $data_arr = [
            'after_sale_id' => $request['to_user_id']
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
            return false;
        }
        // TODO 是否还有订单需要转移
        return true;
    }

    // 售前不能转移
    /*public function preSaleCustomerTransferUpdate($request) {
        // 更新, 直接写sql，将原客服id换成新客服id，where条件要写好
        $sql_where = [
            'id' => ['in', $request['customer_ids']],
            'pre_sale_id' => $request['source_user_id'],
        ];
        $data_arr = [
            'pre_sale_id' => $request['to_user_id']
        ];

        // 事物处理  // TODO 是否还有订单需要转移
        \DB::beginTransaction();
        try {
            $this->theRepository->updateMultiByCondition($sql_where, $data_arr);
            \DB::commit();
        } catch (\Exception $e) {
            $msg = 'update error: ' . $e->getMessage() . ' data:';
            \Log::error($msg, $request);
            \DB::rollBack();
            return false;
        }

        return true;
    }*/

    // 管理员操作客户转移
    public function adminCustomerTransfer() {
        $request = request()->all();
        $responseDto = new ResponseDto();

        // 参数校验数组, 当前登录用户是否有权限暂不验证，后面统一处理
        $rules = [
            'customer_ids' => 'required',
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

        $login_user_info = self::getCurrentLoginUserInfo();
        // 如果是管理员，后续操作有点不一样
        if (1 == $login_user_info['role_id']) {
            ErrorMsg::FillResponseAndLog($responseDto, ErrorMsg::ORDER_OPT_NEED_ADMIN);
            return $responseDto;
        }

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

        // 两个客服必须都是售前或售后
        $department_source = $this->departmentRepository->getInfoById($users_info[$request['source_user_id']]['department_id']);
        $department_to = $this->departmentRepository->getInfoById($users_info[$request['to_user_id']]['department_id']);
        if ($department_to['job_type'] != $department_source['job_type']) {
            // 部门必须相同，要么都是售前、要么都是售后
            ErrorMsg::FillResponseAndLog($responseDto, ErrorMsg::TWO_USER_DEPARTMENT_NOT_MATCH);
            return $responseDto;
        }
        // 如果是售后部门，只需要更新售后客服字段, TODO 还有订单也要一起转移
        if (2 == $department_to['job_type']) {
            $rlt = $this->afterSaleCustomerTransferUpdate($request);
            if (!$rlt) {
                ErrorMsg::FillResponseAndLog($responseDto, ErrorMsg::UPDATE_DB_FAILED);
                return $responseDto;
            }
        } else if (1 == $department_to['job_type']) {
            // 暂不支持客户转移给售前某员工
            ErrorMsg::FillResponseAndLog($responseDto, ErrorMsg::CUSTOMER_TRANSFER_ONLY_BY_AFTER_SALE);
            return $responseDto;
        } else {
            ErrorMsg::FillResponseAndLog($responseDto, ErrorMsg::UNKNOWN_ERROR);
            return $responseDto;
        }


        return $responseDto;
    }

    // 售后客户列表，已分配、未分配只有售后才有
    public function getAfterSaleList($request=null) {
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

        $login_user_info = self::getCurrentLoginUserInfo();
        // 超级管理员能看到所有，不做限制
        if (1 != $login_user_info['role_id']) {
            $department_info = $this->departmentRepository->getInfoById($login_user_info['department_id']);
            if (!$department_info || 2 != $department_info['job_type']) {
                // 只有售后部门才可以
                ErrorMsg::FillResponseAndLog($responseDto, ErrorMsg::CUSTOMER_TRANSFER_ONLY_BY_AFTER_SALE);
                return $responseDto;
            }

            if (2 == $login_user_info['level']) {
                // 员工，只有已分配
                $request['after_sale_id'] = $login_user_info['id'];
            } else {
                // 主管
                /*$staff_dept_list = self::getChildrenDepartmentByDeptId($login_user_info['department_id']);
                //$request['department_id'] = ['in', $staff_dept_list]; // 线索需要使用，客户无此字段

                $user_list = $this->userRepository->getUsersByDepartmentIds($staff_dept_list, 'id');
                if ($user_list) {
                    $user_ids = array_column($user_list, 'id');
                    $request['after_sale_id'] = ['in', $user_ids];    // 客户表只有此字段
                }*/
            }
            // 该部门关联国家
//            $department_weight = (new DepartmentWeightRepository())->getDeptWeightByDeptId($login_user_info['department_id']);
//            $country_ids = [-1];        // 默认没有
//            if ($department_weight) {
//                $country_ids = array_keys($department_weight);
//            }
//            $request['country_id'] = ['in', $country_ids];
        }

        // 获取数据，包含总数字段
        $list = $this->theRepository->getList($request);
        if (!$list || !isset($list[$responseDto::DTO_FIELD_TOTOAL]) || !isset($list[$responseDto::DTO_FIELD_LIST])) {
            ErrorMsg::FillResponseAndLog($responseDto, ErrorMsg::DATA_EMPTY);
            return $responseDto;
        }
        if ($list[$responseDto::DTO_FIELD_LIST]) {
            // 成功，返回列表信息
            foreach ($list[$responseDto::DTO_FIELD_LIST] as $key => $v_detail) {
                $v_info = new CustomerDto();
                $v_info->Assign($this->addAllAttrName2Data($v_detail));
                $list[$responseDto::DTO_FIELD_LIST][$key] = $v_info;
            }
        }
        $data_list = new DataListDto();
        if (!isset($request[$responseDto::WITHOUT_ORDER_STATS]) || !$request[$responseDto::WITHOUT_ORDER_STATS]) {
            // 默认情况，所有客户列表都要带上订单统计数据；如果设置不需要携带统计数据则跳过
            $redis_stats_data = $this->theRepository->getCustomerNumStatsByUserId($login_user_info);
            $customer_stat = new CustomerStatsDto();
            $customer_stat->Assign($redis_stats_data); // 从redis缓存获取数据
            $data_list->meta[$responseDto::DTO_FIELD_NUMBER_STATS] = $customer_stat;
        }
        $data_list->Assign($list);
        $responseDto->data = $data_list;

        return $responseDto;
    }

    public function distributed() {
        $request = request()->all(); // 参数接收
        $request['distribution_status'] = 1;
        return $this->getAfterSaleList($request);
    }

    public function distributeNot() {
        $request = request()->all(); // 参数接收
        $request['distribution_status'] = 0;
        return $this->getAfterSaleList($request);
    }
}
