<?php

namespace App\Services\Customer;

use App\Libs\Utils\ErrorMsg;
use App\Dto\DataListDto;
use App\Dto\ResponseDto;
use App\Dto\CustomerAddressDto;
use App\Models\Admin\User;
use App\Repositories\Admin\DepartmentRepository;
use App\Repositories\Customer\CustomerAddressRepository;
use App\Repositories\Admin\UserRepository;
use App\Repositories\Customer\CustomerRepository;
use App\Services\BaseService;
use Illuminate\Support\Facades\Validator;


class CustomerAddressService extends BaseService
{
    protected $theRepository;
    protected $departmentRepository;
    protected $customerRepository;

    public function __construct() {
        $this->theRepository = new CustomerAddressRepository();
        $this->departmentRepository = new DepartmentRepository();
        $this->customerRepository = new CustomerRepository();
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
        // 依据身份，返回不同数据
        $login_user_info = self::getCurrentLoginUserInfo();
        if (1 != $login_user_info['role_id']) {
            $department_info = $this->departmentRepository->getInfoById($login_user_info['department_id']);
            // 售前不做限制; 售后有客户分配的功能，只能操作指定的客户
            // 如果是售后客服人员，则只能返回分配给他的客户，如果是售后主管身份能看到该部门下所有员工关联客户
            if ($department_info && 2 == $department_info['job_type']) {
                // 当前用户如果是员工，则只返回自己
                if (User::LEVEL_STAFF == $login_user_info['level']) {
                    $request['after_sale_id'] = $login_user_info['id'];
                } else {
                    // 主管的话，找到主管所属部门，此部门下所有员工，包括各级子部门的员工
                    $user_ids = parent::getChildrenUserIdsByDeptId($login_user_info['department_id']);
                    if ($user_ids) {
                        $request['after_sale_id'] = ['in', $user_ids];
                    } else {
                        $request['after_sale_id'] = -1; // 确保筛查的数据为0 // TODO 减少查询，可优化
                    }
                }
            } else if (1 == $department_info['job_type']) {
                // 售前
                if (User::LEVEL_STAFF == $login_user_info['level']) {
                    $request['pre_sale_id'] = $login_user_info['id'];
                } else {
                    // 当前账号所在部门的全部员工id，包括所有子部门
                    $user_ids = parent::getChildrenUserIdsByDeptId($login_user_info['department_id']);
                    if ($user_ids) {
                        $request['pre_sale_id'] = ['in', $user_ids];
                    } else {
                        $request['pre_sale_id'] = -1; // 确保筛查的数据为0
                    }
                }
            }
        }

        // 获取数据，包含总数字段
        $list = $this->theRepository->getList($request);
        if (!$list || !isset($list[$responseDto::DTO_FIELD_TOTOAL]) || !isset($list[$responseDto::DTO_FIELD_LIST])) {
            ErrorMsg::FillResponseAndLog($responseDto, ErrorMsg::DATA_EMPTY);
            return $responseDto;
        }
        if ($list[$responseDto::DTO_FIELD_LIST]) {
            // 需连接customer客户表，获取所有的 customer_id 列表
            $customer_ids = [];
            foreach ($list[$responseDto::DTO_FIELD_LIST] as $key => $v_detail) {
                $customer_ids[] = $v_detail['id'];
            }
            // 通过customer_id，获取对应的客户数据
            $customer_list = $this->customerRepository->getListByIds($customer_ids); // 批量获取

            // 成功，返回列表信息
            foreach ($list[$responseDto::DTO_FIELD_LIST] as $key => $v_detail) {
                $customer_info = $customer_list[$v_detail['customer_id']] ?? [];
                $v_info = new CustomerAddressDto();
                $v_info->Assign($customer_info); // 客户其他信息增加进去, 放在前面，避免自增ID被替换掉，客户id相同但两个地址不同
                $v_info->Assign($this->addAttrName2Data($v_detail));
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
        //$customer_info = $this->customerRepository->getInfoById($v_detail['customer_id']);
        $v_info = new CustomerAddressDto();
        //$v_info->Assign($customer_info);
        $v_info->Assign($this->addAttrName2Data($v_detail));
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

    // 某客户的地址
    public function getListByCid($id) {
        $request = request()->all();
        $request['customer_id'] = $id;

        if (!is_numeric($id) || $id <= 0) {
            $responseDto = new ResponseDto();
            ErrorMsg::FillResponseAndLog($responseDto, ErrorMsg::PARAM_ERROR);
            return $responseDto;
        }

        return self::getList($request);
    }

    // 某客户的地址, 返回全部，不分页（最多也不能超过1000条）
    public function getListByCidAll($id) {
        $request = request()->all();
        $request['customer_id'] = $id;
        $request['limit'] = 1000;

        if (!is_numeric($id) || $id <= 0) {
            $responseDto = new ResponseDto();
            ErrorMsg::FillResponseAndLog($responseDto, ErrorMsg::PARAM_ERROR);
            return $responseDto;
        }

        return self::getList($request);
    }
}
