<?php

namespace App\Services\Customer;

use App\Libs\Utils\ErrorMsg;
use App\Dto\DataListDto;
use App\Dto\ResponseDto;
use App\Dto\CustomerDistributeDto;
use App\Models\Admin\User;
use App\Repositories\Admin\DepartmentRepository;
use App\Repositories\Customer\CustomerDistributeRepository;
use App\Repositories\Admin\UserRepository;
use App\Repositories\Customer\CustomerRepository;
use App\Repositories\Customer\CustomerServiceRelationRepository;
use App\Services\BaseService;
use Illuminate\Support\Facades\Validator;


class CustomerDistributeService extends BaseService
{
    protected $theRepository;
    protected $customerRepository;
    protected $departmentRepository;
    protected $customerServiceRelationRepository;

    public function __construct() {
        $this->theRepository = new CustomerDistributeRepository();
        $this->customerRepository = new CustomerRepository();
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

        // 获取数据，包含总数字段
        $list = $this->theRepository->getList($request);
        if (!$list || !isset($list[$responseDto::DTO_FIELD_TOTOAL]) || !isset($list[$responseDto::DTO_FIELD_LIST])) {
            ErrorMsg::FillResponseAndLog($responseDto, ErrorMsg::DATA_EMPTY);
            return $responseDto;
        }
        if ($list[$responseDto::DTO_FIELD_LIST]) {
            // 成功，返回列表信息
            foreach ($list[$responseDto::DTO_FIELD_LIST] as $key => $v_detail) {
                $v_info = new CustomerDistributeDto();
                $v_info->Assign($v_detail);
                $list[$responseDto::DTO_FIELD_LIST][$key] = $v_info;
            }
        }
        $data_list = new DataListDto();
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
        // 成功，返回信息
        $v_info = new CustomerDistributeDto();
        $v_info->Assign($v_detail);
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

    // 分配客户
    public function distributeCustomer($request = null) {
        if (!$request) $request = request()->all();
        $responseDto = new ResponseDto();

        $curr_datetime = date('Y-m-d H:i:s');
        if ('cli' != php_sapi_name()) $current_uid = auth('api')->id();
        else $current_uid = ($request['creator_id'] ?? 0) + 0;
        // 参数校验数组, 当前登录用户是否有权限暂不验证，后面统一处理
        //$field_id = 'id';
        $rules = [
            'ids'        => 'required',                     // 客户ID列表
            'to_user_id' => 'required|integer|min:1',       // 要转移的客服
        ];
        $validate = Validator::make($request, $rules);
        if ($validate->fails()) {
            $error_list = $validate->errors()->all();
            $responseDto->status = ErrorMsg::PARAM_ERROR;
            $responseDto->msg = implode("\r\n", $error_list);
            return $responseDto;
        }

        // 当前登录者必须是主管(只有直接主管可以分配给员工，不能跨级分配，超级管理员咱不能分配)
        $login_user_info = self::getCurrentLoginUserInfo();
        if (!$login_user_info || 1 == $login_user_info['role_id'] || User::LEVEL_ADMIN != $login_user_info['level']) {
            // 只有主管才可以
            ErrorMsg::FillResponseAndLog($responseDto, ErrorMsg::ORDER_OPT_NEED_ADMIN);
            return $responseDto;
        }

        // 必须是售后部门
        if (!$this->departmentRepository) $this->departmentRepository = new DepartmentRepository();
        $department_info = $this->departmentRepository->getInfoById($login_user_info['department_id']);
        if (2 != $department_info['job_type']) {
            // 只有售后部门才可以
            ErrorMsg::FillResponseAndLog($responseDto, ErrorMsg::DEPARTMENT_MUST_AFTER_SALE);
            return $responseDto;
        }

        // 检查客服id是否存在
        $user_info = $this->userRepository->getUserById($request['to_user_id']);
        // 目标用户不存在
        if (!$user_info) {
            ErrorMsg::FillResponseAndLog($responseDto, ErrorMsg::USER_TO_NOT_EXISTS);
            return $responseDto;
        }
        // 被分配人必须是客服员工
        if (User::LEVEL_STAFF != $user_info['level']) {
            ErrorMsg::FillResponseAndLog($responseDto, ErrorMsg::USER_MUST_STAFF);
            return $responseDto;
        }
        // 只有直接主管可以分配给员工，不能跨级分配，只需要判断两者所属部门一样即可
        if ($login_user_info['department_id'] != $user_info['department_id']) {
            ErrorMsg::FillResponseAndLog($responseDto, ErrorMsg::ADMIN_USER_DEPARTMENT_NOT_MATCH);
            return $responseDto;
        }

        // 检查这些客户id是否存在
        $customer_list = $this->customerRepository->getListByIds($request['ids']);
        if (!$customer_list) {
            ErrorMsg::FillResponseAndLog($responseDto, ErrorMsg::DATA_NOT_EXISTS);
            return $responseDto;
        }

        // 更新, 直接写sql，将原客服id=0换成新客服id，where条件要写好
        $rlt = $this->afterSaleCustomerDistribute($request, $curr_datetime, $login_user_info, $customer_list, $user_info, $department_info['job_type']);
        if (!$rlt) {
            ErrorMsg::FillResponseAndLog($responseDto, ErrorMsg::UPDATE_DB_FAILED);
            return $responseDto;
        }
        return $responseDto;
    }


    // 更新数据
    private function afterSaleCustomerDistribute($request, $created_time, $login_user_info, $customer_list,$to_user_info, $job_type) {
        $creator_id = $login_user_info['id'];

        // 更新, 直接写sql，将原客服id=0换成新客服id，where条件要写好
        $sql_where = [
            'id' => ['in', $request['ids']],
            'after_sale_id' => 0,
        ];
        $data_arr = [
            'after_sale_id' => $request['to_user_id'],
            'updator_id' => $creator_id,
            'distribution_status' => 1                    // 状态为已分配
        ];

        // 需要插入分配记录
        $distribute_data = [];
        $relation_data = [];
        foreach ($request['ids'] as $customer_id) {
            $tel = '';
            if (isset($customer_list[$customer_id]) && isset($customer_list[$customer_id]['tel']))
                $tel = $customer_list[$customer_id]['tel'];

            $one_distribute = [
                'part' => $creator_id % 10,
                'distribute_user_id' => $creator_id,
                'pre_distribute_id' => 0,
                'department_id' => $login_user_info['department_id'],
                'customer_id' => $customer_id,
                'tel' => $tel,
                'type' => $job_type,                                    // 类别，1-售前 2-售后
                'distributed_user_id' => $request['to_user_id'],
                'distribute_type' => 1,                                 // 分配类型：0-手动 1-自动
                'status' => 1,                                          // 默认已分配
                'created_time' => $created_time,
                'distributed_time' => $created_time,
                'creator_id' => $creator_id,
                'updator_id' => $creator_id,
            ];
            $distribute_data[] = $one_distribute;

            // 客户客服关联表
            $one_relation = [
                'part' => $to_user_info['department_id'] % 10, // department_id按10取模
                'customer_id' => $customer_id,
                'service_id' => $to_user_info['id'],
                'department_id' => $to_user_info['department_id'],
                'relation_type' => $job_type,                           // 类别，1-售前 2-售后
                'status' => 1,                                          // 默认有效
                'created_time' => $created_time,
                'updated_time' => $created_time,
                'creator_id' => $creator_id,
                'updator_id' => $creator_id,
            ];
            $relation_data[] = $one_relation;
        }

        if (!$this->customerServiceRelationRepository) $this->customerServiceRelationRepository = new CustomerServiceRelationRepository();

        // 事务处理
        \DB::beginTransaction();
        try {
            $this->customerRepository->updateMultiByCondition($sql_where, $data_arr);
            // 需要插入多条分配记录
            $this->theRepository->insertMultiple($distribute_data);
            // 插入多条关联关系记录
            $this->customerServiceRelationRepository->insertMultiple($relation_data);
            \DB::commit();
        } catch (\Exception $e) {
            $msg = 'update error: ' . $e->getMessage() . ' data:';
            \Log::error($msg, $request);
            \DB::rollBack();
            return false;
        }
        return true;
    }

}
