<?php

namespace App\Services\Admin;

use App\Dto\DepartmentWeightDto;
use App\Libs\Utils\ErrorMsg;
use App\Dto\DataListDto;
use App\Dto\ResponseDto;
use App\Dto\DepartmentDto;
use App\Models\Admin\Department;
use App\Models\Admin\User;
use App\Repositories\Admin\DepartmentRepository;
use App\Repositories\Admin\DepartmentWeightRepository;
use App\Repositories\Admin\UserRepository;
use App\Services\BaseService;
use Illuminate\Support\Facades\Validator;


class DepartmentService extends BaseService
{
    protected $theRepository;
    protected $departmentRepository;
    protected $departmentWeightRepository;
    protected $deptWeightRepository;

    public function __construct() {
        $this->theRepository = new DepartmentRepository();
        $this->departmentWeightRepository = new DepartmentWeightRepository();
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
        if (!isset($request['limit'])) $request['limit'] = 500;

        $login_user_info = self::getCurrentLoginUserInfo();
        if (1 != $login_user_info['role_id']) {
            $my_dept_list = self::getChildrenDepartmentByDeptId($login_user_info['department_id']);
            $request['id'] = ['in', $my_dept_list];
        }

        // 获取数据，包含总数字段
        $list = $this->theRepository->getList($request);
        if (!$list || !isset($list[$responseDto::DTO_FIELD_TOTOAL]) || !isset($list[$responseDto::DTO_FIELD_LIST])) {
            ErrorMsg::FillResponseAndLog($responseDto, ErrorMsg::DATA_EMPTY);
            return $responseDto;
        }
        $list[$responseDto::DTO_FIELD_LIST] = self::getDepartmentWithWeight($list[$responseDto::DTO_FIELD_LIST]);

        if ($list[$responseDto::DTO_FIELD_LIST]) {
            // 重新拼装一下层次关系，将子节点放到 children 字段上
            $list[$responseDto::DTO_FIELD_LIST] = $this->getDepartmentTree($list[$responseDto::DTO_FIELD_LIST],$list[$responseDto::DTO_FIELD_LIST][0]['parent_id']);
            self::appendTotalUserNum($list[$responseDto::DTO_FIELD_LIST]); // 附加总数字段
        }

        $data_list = new DataListDto();
        $data_list->Assign($list);
        $responseDto->data = $data_list;

        return $responseDto;
    }

    // 拼装部门下各个国家的权重数据 TODO 加缓存
    public function getDepartmentWithWeight($orig_department_list) {
        // 也可放到逻辑层，因为需要调用不同的service；
        // 部门还需要联合权重表，一个部门可能对应多个国家
        $dept_weight_all = $this->departmentWeightRepository->getAllDepartmentWeight([0, 1]); // 只需要启用、停用状态
        if ($dept_weight_all) {
            // 按照部门id归类
            $dept_weight_list = [];
            foreach ($dept_weight_all as $row1) {
                $dept_weight_list[$row1['department_id']][] = $row1;
            }
        }

        foreach ($orig_department_list as $key => &$row) {
            if (!isset($dept_weight_list[$row['id']]))
                continue;

            // 每个部门都必须有存在的国家和权重，否则不返回？可以返回，便于排查问题
            foreach ($dept_weight_list[$row['id']] as $v_detail) {
                $v_info = new DepartmentWeightDto();
                $v_info->Assign($this->addAttrName2Data($v_detail,[],['country_id','department_id','status']));
                $row['country_weight_ratio'][] = $v_info; // 加一个字段
            }
        }
        return $orig_department_list;
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

        // 必须有国家数据，除了删除的时候不需要国家
        if ((!isset($request['status']) || $request['status'] != '-1') && (!isset($request['data']) || !$request['data'])) {
            ErrorMsg::FillResponseAndLog($responseDto, ErrorMsg::DEPARTMENT_COUNTRY_NEEDED);
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
            // 部门的上下级关系不能修改、部门类型不能修改，否则提示错误
            if (isset($request['parent_id']) && $request['parent_id'] != $v_detail['parent_id']) {
                ErrorMsg::FillResponseAndLog($responseDto, ErrorMsg::DEPARTMENT_PARENT_CAN_NOT_MODIFY);
                return $responseDto;
            }
            if (isset($request['job_type']) && $request['job_type'] != $v_detail['job_type']) {
                ErrorMsg::FillResponseAndLog($responseDto, ErrorMsg::DEPARTMENT_JOB_TYPE_CAN_NOT_MODIFY);
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
            // 如果有上级部门，需要校对跟上级部门的岗位类型一致、国家设置不能超出父级的国家设置
            if (isset($data_arr['parent_id']) && $data_arr['parent_id']) {
                // 获取上级部门的信息
                $parent_detail = $this->theRepository->getInfoById($request['parent_id']);
                if (!$parent_detail) {
                    ErrorMsg::FillResponseAndLog($responseDto, ErrorMsg::PARENT_DATA_NOT_EXISTS);
                    return $responseDto;
                }
                if ($parent_detail['job_type'] != $request['job_type']) {
                    ErrorMsg::FillResponseAndLog($responseDto, ErrorMsg::DEPARTMENT_JOB_TYPE_NOT_MATCH);
                    return $responseDto;
                }
                if (isset($data_arr['data']) && $data_arr['data']) {
                    // 国家范围不能超过父级国家范围：
                    $dept_weight = $this->departmentWeightRepository->getDeptWeightByDeptId($request['parent_id']);
                    if ($dept_weight) {
                        $parent_countrys = array_column($dept_weight, 'country_id');
                        $new_countrys = array_column($data_arr['data'], 'country_id');
                        $add_ids = array_diff($new_countrys, $parent_countrys);         // 新增的
                        if ($add_ids) {
                            // 分单国家超过上级部门分单国家范围
                            ErrorMsg::FillResponseAndLog($responseDto, ErrorMsg::DEPARTMENT_COUNTRY_OVER);
                            return $responseDto;
                        }
                    }
                }
            }

            $data_arr['distribute_type'] = Department::DISTRIBUTE_TYPE_1;   // 默认都是自动分配
            $data_arr['creator_id'] = $current_uid;
            $data_arr['updator_id'] = $data_arr['creator_id'];
            $data_arr['created_time'] = $curr_datetime;
            //$data_arr['deleted_time'] = $this->theRepository::DATETIME_NOT_NULL_DEFAULT;
        }
        // 数据增加几个默认值
        $data_arr['updated_time'] = $curr_datetime;

        // 父级分单方式需要自动维护：如果存在上级，需要将上级部门的分单方式改成自动
        if (isset($request['id']) && $request['id']) {
            // 更新
            $rlt = $this->updateDepartmentAndWeightData($data_arr, $request, $curr_datetime, $current_uid);
            if (!$rlt) {
                ErrorMsg::FillResponseAndLog($responseDto, ErrorMsg::UPDATE_DB_FAILED);
                return $responseDto;
            }
            $this->updateParentDistribute($v_detail['parent_id']); // 上级部门分单方式维护
        } else {
            if (!isset($data_arr['status'])) $data_arr['status'] = 1;    // 默认启用
            if (!isset($data_arr['parent_id'])) $data_arr['parent_id'] = 0;    // 默认0

            $v_id = $this->insertDepartmentAndWeight($data_arr, $request, $curr_datetime, $current_uid);
            if (!$v_id) {
                ErrorMsg::FillResponseAndLog($responseDto, ErrorMsg::INSERT_DB_FAILED);
                return $responseDto;
            }
            try {
                $this->updateParentDistribute($data_arr['parent_id'] ?? 0); // 上级部门分单方式维护
            } catch (\Exception $e){}

            // 暂不返回详情，前端跳列表页
            $responseDto->data = ['id'=>$v_id];
        }
        $this->theRepository->deleteAllListCache(); // 数据有变化，更新全量缓存；

        return $responseDto;
    }

    // 上级部门分单方式由手动改为自动，无需事务，带上上级部门的手动方式一起即可
    private function updateParentDistribute($parent_id) {
        if (!$parent_id)
            return 1;
        $condition  = ['distribute_type' => Department::DISTRIBUTE_TYPE_0]; // 手动
        $update_arr = ['distribute_type' => Department::DISTRIBUTE_TYPE_1]; // 自动
        return $this->theRepository->updateByIdAndCondition($parent_id, $update_arr, $condition);
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

        // 需要带上子部门，因此直接从总列表中获取好了
        $all_dept_list = $this->theRepository->getAllDepartment();
        if (!$all_dept_list) {
            ErrorMsg::FillResponseAndLog($responseDto, ErrorMsg::DATA_EMPTY);
            return $responseDto;
        }
        $all_dept_list = self::getDepartmentWithWeight($all_dept_list); // 拼接上权重对象

        // 重新拼装一下层次关系，将子节点放到 children 字段上
        $all_dept_list = $this->getDepartmentTree($all_dept_list, 0, 1);
        self::appendTotalUserNum($all_dept_list); // 附加总数字段

        // 逐个节点检查，从树上摘取
        $v_info = $this->getDepartmentTreeByDeptid($all_dept_list, $request['id']);

        if (!$v_info) {
            ErrorMsg::FillResponseAndLog($responseDto, ErrorMsg::DATA_EMPTY);
            return $responseDto;
        }
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

    // 获取树形结构
    public function getDepartmentTree($data, $pid = 0, $level = 1) {
        $arr = [];
        foreach ($data as $v) {
            if ($v['parent_id'] == $pid) {
                $v['level'] = $level;
                $v['children'] = $this->getDepartmentTree($data, $v['id'], $level + 1);
                $v['user_num_self_level'] = $this->userRepository->getDepartmentUserNum($v['id']);
                $v_info = new DepartmentDto();
                $v_info->Assign($this->addAttrName2Data($v));
                $arr[] = $v_info;
            }
        }
        return $arr;
    }

    public function getDepartmentTreeByDeptid($all_dept_list, $dept_id=0) {
        $arr = [];
        foreach ($all_dept_list as $row) {
            if ($dept_id == $row->id) {
                return $row;
            }
            if ($row->children) {
                $arr = self::getDepartmentTreeByDeptid($row->children, $dept_id);
                if ($arr) return $arr;
            }
        }
        return $arr;
    }

    public function insertDepartmentAndWeight($data_arr, $request, $create_time='', $creator_id=0) {
        if (!$this->deptWeightRepository) $this->deptWeightRepository = new DepartmentWeightRepository();

        \DB::beginTransaction();
        try {
            $insert_id = $this->theRepository->insertGetId($data_arr);
            //if (!$insert_id) throw new \Exception(' transaction insertGetId error!'); // 似乎并不会执行
            $this->deptWeightRepository->insertDepartWeightMultiByDeptId($insert_id, $request, $create_time, $creator_id);
            \DB::commit();
        } catch (\Exception $e) {
            $msg = 'db-Transaction-error: table, ' . $this->theRepository->getModel()->getTable() . ', ' .
                $this->deptWeightRepository->getModel()->getTable() . ' error: ' . $e->getMessage() . ' data:';
            \Log::error($msg, $request);
            \DB::rollBack();
            return false;
        }
        return $insert_id;
    }

    public function updateDepartmentAndWeightData($data_arr, $request, $create_time='', $creator_id=0) {
        if (!$this->deptWeightRepository) $this->deptWeightRepository = new DepartmentWeightRepository();

        \DB::beginTransaction();
        try{
            $this->deptWeightRepository->updateDepartmentWeightMultiByDeptId($request['id'], $request, $create_time, $creator_id);
            $this->theRepository->updateData($request['id'], $data_arr);
            \DB::commit();
        } catch (\Exception $e) {
            $msg = 'db-Transaction-error: table, ' . $this->theRepository->getModel()->getTable() . ', ' .
                $this->deptWeightRepository->getModel()->getTable() . ' error: ' . $e->getMessage() . ' data:';
            \Log::error($msg, $request);
            \DB::rollBack();
            return false;
        }
        return true;
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


    // 增加部门下员工总数值
    public static function appendTotalUserNum(&$tree) {
        if (!$tree) return ;
        foreach ($tree as &$v) {
            if ($v->children) {
                self::appendTotalUserNum($v->children);
                $v->user_num_all_level = $v->user_num_self_level + self::getDepartmentUserNum($v->children);
            } else {
                $v->user_num_all_level = $v->user_num_self_level;
            }
            //$v->department_staff_num = $v->user_num_all_level; // 旧字段，准备废弃
        }
        return ;
    }
    // 获取department-id下级的所有用户数，user_num_self_level字段累加
    public static function getDepartmentUserNum($tree_data) {
        $user_num_all = 0;
        if (!$tree_data) return $user_num_all;

        foreach ($tree_data as $v) {
            $user_num_all += $v->user_num_self_level;
            if ($v->children) {
                $user_num_all += self::getDepartmentUserNum($v->children);
            }
        }
        return $user_num_all;
    }

    // 我的部门列表 TODO 废弃，上面已经是这样写的
    public function myDepartmentList() {
        $request = request()->all();

        $login_user_info = self::getCurrentLoginUserInfo();
        $my_dept_list = self::getChildrenDepartmentByDeptId($login_user_info['department_id']);
        $request['id'] = ['in', $my_dept_list];

        return $this->getList($request);
    }
}
