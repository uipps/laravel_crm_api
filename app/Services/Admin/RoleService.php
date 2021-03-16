<?php

namespace App\Services\Admin;

use App\Libs\Utils\ErrorMsg;
use App\Dto\DataListDto;
use App\Dto\ResponseDto;
use App\Dto\RoleDto;
use App\Models\Admin\User;
use App\Repositories\Admin\RolePrivilegeRepository;
use App\Repositories\Admin\RoleRepository;
use App\Repositories\Admin\UserRepository;
use App\Services\BaseService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;


class RoleService extends BaseService
{
    protected $theRepository;
    protected $rolePrivilegeRepository;

    public function __construct() {
        $this->theRepository = new RoleRepository();
        $this->userRepository = new UserRepository(); // 用于权限检查
    }

    public function getList() {
        $request = request()->all(); // 参数接收
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
        // 获取全部角色关联的有效用户数
        //$all_role_list = self::getRolesValidUserNum();

        if ($list[$responseDto::DTO_FIELD_LIST]) {
            // 成功，返回列表信息

            // 所有的role_id列表
            $role_ids = [];
            foreach ($list[$responseDto::DTO_FIELD_LIST] as $key => $v_detail) {
                $role_ids[] = $v_detail['id'];
            }

            // 通过角色id，获取权限和对应的routing数据，
            $role_list_routings = $this->theRepository->getRolesUrlsPrivilegesByRoleIdList($role_ids); // 批量获取
            foreach ($list[$responseDto::DTO_FIELD_LIST] as $key => $v_detail) {
                //  $role_routing = $this->theRepository->getRoleUrlPrivilegeByRoleId($v_detail['id']); // 改为批量获取
                $role_routing = $role_list_routings[$v_detail['id']] ?? [];
                $v_info = new RoleDto();
                $v_info->Assign($v_detail);
                $v_info->role_privileges = isset($role_routing['privilege_list']) ? array_keys($role_routing['privilege_list']) : [];
                $v_info->role_routings = isset($role_routing['routing_list']) ? array_keys($role_routing['routing_list']) : [];
                $v_info->relate_valid_user = $this->theRepository->getRoleValidUserNum($v_detail['id']);
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

        // 员工不能创建和修改
        $login_user_info = self::getCurrentLoginUserInfo();
        if (User::LEVEL_STAFF == $login_user_info['level']) {
            ErrorMsg::FillResponseAndLog($responseDto, ErrorMsg::NO_PRIVILEGE);
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
            $rlt = $this->updateRoleAndPrivilegeData($data_arr, $request, $curr_datetime, auth('api')->id());
            if (!$rlt) {
                ErrorMsg::FillResponseAndLog($responseDto, ErrorMsg::UPDATE_DB_FAILED);
                return $responseDto;
            }
        } else {
            if (!isset($data_arr['status'])) $data_arr['status'] = 1;    // 默认启用
            $v_id = $this->insertRoleAndPrivilegeData($data_arr, $request, $curr_datetime, $current_uid);
            if (!$v_id) {
                ErrorMsg::FillResponseAndLog($responseDto, ErrorMsg::INSERT_DB_FAILED);
                return $responseDto;
            }
            // 暂不返回详情，前端跳列表页
            $responseDto->data = ['id'=>$v_id];
        }
        $this->theRepository->deleteAllListCache(); // 数据有变化，更新全量缓存；

        return $responseDto;
    }

    // 插入角色权限，角色权限数据放在另一张表，需要进行事务处理
    public function insertRoleAndPrivilegeData($data_arr, $request, $create_time='', $creator_id=0) {
        \DB::beginTransaction();
        try {
            $user_id = $this->theRepository->insertGetId($data_arr);
            if (!$user_id)
                return false;
            $this->theRepository->insertMultiPrivilegeByRoleid($user_id, $request, $create_time, $creator_id);
            \DB::commit();
        } catch (\Exception $e) {
            $msg = 'db-Transaction-error: table, ' . $this->theRepository->getModel()->getTable() . ', ' .
                $this->theRepository->getRolePrivilegeModel()->getTable() . ' error: ' . $e->getMessage() . ' data:';
            Log::error($msg, $request);
            \DB::rollBack();
            return false;
        }
        return $user_id;
    }

    public function updateOne($id) {
        $responseDto = new ResponseDto();

        // 参数校验数组, 当前登录用户是否有权限暂不验证，后面统一处理
        $rules = [
            'id' => 'required|integer|min:1'
        ];
        $validate = Validator::make(['id'=>$id], $rules);
        if ($validate->fails()) {
            $error_list = $validate->errors()->all();
            $responseDto->status = ErrorMsg::PARAM_ERROR;
            $responseDto->msg = implode("\r\n", $error_list);
            return $responseDto;
        }
        // 参数
        $request = request()->all();
        $request['id'] = $id;

        return self::addOrUpdate($request);
    }

    // 更新角色权限，需要进行事务处理
    public function updateRoleAndPrivilegeData($data_arr, $request, $create_time='', $creator_id=0) {
        \DB::beginTransaction();
        try{
            $this->theRepository->updateMultiPrivilegeByRoleid($request['id'], $request, $create_time, $creator_id); // 优先更新用户属性表
            $this->theRepository->updateData($request['id'], $data_arr);
            \DB::commit();
        } catch (\Exception $e) {
            $msg = 'db-Transaction-error: table, ' . $this->theRepository->getModel()->getTable() . ', ' .
                $this->theRepository->getRolePrivilegeModel()->getTable() . ' error: ' . $e->getMessage() . ' data:';
            Log::error($msg, $request);
            \DB::rollBack();
            return false;
        }
        return true;
    }

    public function detail($id){
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
        // 通过角色id，获取权限和对应的routing数据
        $role_routing = $this->theRepository->getRoleUrlPrivilegeByRoleId($request[$field_id]);

        // 获取全部
        /*$all_role_list = self::getRolesValidUserNum();
        if (isset($all_role_list[$id])) {
            $v_detail['relate_valid_user'] = $all_role_list[$id]; // 直接赋值
        }*/

        // 成功，返回信息
        $v_info = new RoleDto();
        $v_info->Assign($v_detail);
        $v_info->role_privileges = isset($role_routing['privilege_list']) ? array_keys($role_routing['privilege_list']) : [];
        $v_info->role_routings = isset($role_routing['routing_list']) ? array_keys($role_routing['routing_list']) : [];
        $v_info->relate_valid_user = $this->theRepository->getRoleValidUserNum($request['id']);
        $responseDto->data = $v_info;

        return $responseDto;
    }

    // 直接删除
    public function delete($id) {
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

        // 通过角色id，删除对应的权限，这里有事务处理
        if (!$this->deleteRoleAndPrivilegeData($request)) {
            ErrorMsg::FillResponseAndLog($responseDto, ErrorMsg::DELETE_DB_FAILED);
            return $responseDto;
        }

        return $responseDto;
    }

    // 删除角色权限，需要进行事务处理
    public function deleteRoleAndPrivilegeData($request) {
        if (!$this->rolePrivilegeRepository) $this->rolePrivilegeRepository = new RolePrivilegeRepository();

        \DB::beginTransaction();
        try {
            $this->rolePrivilegeRepository->deleteMultiPrivilegeByRoleid($request['id']);
            $this->theRepository->delete($request['id']);
            \DB::commit();
        } catch (\Exception $e) {
            $msg = 'db-Transaction-error: table, ' . $this->theRepository->getModel()->getTable() . ', ' .
                $this->theRepository->getRolePrivilegeModel()->getTable() . ' error: ' . $e->getMessage() . ' data:';
            Log::error($msg, $request);
            \DB::rollBack();
            return false;
        }
        return true;
    }

    // 获取角色的有效用户数，支持批量，加缓存
    /*public function getRolesValidUserNum() {
        // 获取全部角色ID，然后去userAttr表查
        $role_list = $this->theRepository->getAllRole();

    }*/
}
