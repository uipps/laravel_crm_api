<?php

namespace App\Services\Customer;

use App\Dto\CustomerStatsDto;
use App\Libs\Utils\ErrorMsg;
use App\Dto\DataListDto;
use App\Dto\ResponseDto;
use App\Dto\CustomerClueDto;
use App\Models\Admin\User;
use App\Repositories\Admin\DepartmentRepository;
use App\Repositories\Admin\RolePrivilegeRepository;
use App\Repositories\Customer\CustomerClueDistributeRepository;
use App\Repositories\Customer\CustomerClueRepository;
use App\Repositories\Admin\UserRepository;
use App\Repositories\Customer\CustomerClueTrackRepository;
use App\Repositories\Customer\CustomerRepository;
use App\Services\BaseService;
use Illuminate\Support\Facades\Validator;


class CustomerClueService extends BaseService
{
    protected $theRepository;
    protected $departmentRepository;
    protected $customerClueTrackRepository;
    protected $customerClueDistributeRepository;
    protected $rolePrivilegeRepository;

    public function __construct() {
        $this->theRepository = new CustomerClueRepository();
        $this->customerClueTrackRepository = new CustomerClueTrackRepository();
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
        $login_user_info = self::getCurrentLoginUserInfo(); // TODO 当前登录用户是否有权限，统一一个方法放到BaseService中

        // 获取数据，包含总数字段
        $list = $this->theRepository->getList($request);
        if (!$list || !isset($list[$responseDto::DTO_FIELD_TOTOAL]) || !isset($list[$responseDto::DTO_FIELD_LIST])) {
            ErrorMsg::FillResponseAndLog($responseDto, ErrorMsg::DATA_EMPTY);
            return $responseDto;
        }
        if ($list[$responseDto::DTO_FIELD_LIST]) {
            // 成功，返回列表信息
            foreach ($list[$responseDto::DTO_FIELD_LIST] as $key => $v_detail) {
                $v_info = new CustomerClueDto();
                $v_detail = $this->addAttrName2Data($v_detail);
                $v_detail = $this->addCreatorName($v_detail);
                $v_detail = $this->addPostSaleIdName($v_detail);
                $v_info->Assign($v_detail);
                $list[$responseDto::DTO_FIELD_LIST][$key] = $v_info;
            }
        }
        $data_list = new DataListDto();
        if (!isset($request[$responseDto::WITHOUT_ORDER_STATS]) || !$request[$responseDto::WITHOUT_ORDER_STATS]) {
            // 默认情况，所有客户列表都要带上订单统计数据；如果设置不需要携带统计数据则跳过
            $redis_stats_data = (new CustomerRepository())->getCustomerNumStatsByUserId($login_user_info);
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

        $curr_datetime = date('Y-m-d H:i:s');
        // 参数校验数组, 当前登录用户是否有权限暂不验证，后面统一处理
        //$field_id = 'id';
        $rules = [
            'id' => 'sometimes|integer',
        ];
        if (!isset($request['id'])) {
            $rules['name']        = 'required|string';        // 活动名称
            $rules['country_id']  = 'required|integer|min:1'; // 1-可叠加 2-不可叠加
            $rules['language_id'] = 'required|integer|min:1';
            $rules['advisory_type'] = 'required|integer|min:1';
            $rules['quality_level'] = 'required|integer|min:1';
            $rules['clue_source'] = 'required|url';
            //$rules['remark']      = 'required|string';

            // 三个字段必须有一个 "facebook_id":"", "whatsapp_id":"", "line_id":"",
            $rules['facebook_id'] = 'required_without_all:whatsapp_id,line_id'; // |exists:facebooks,id
            $rules['whatsapp_id'] = 'required_without_all:facebook_id,line_id';
            $rules['line_id']     = 'required_without_all:whatsapp_id,facebook_id';
        }
        $validate = Validator::make($request, $rules);
        if ($validate->fails()) {
            $error_list = $validate->errors()->all();
            $responseDto->status = ErrorMsg::PARAM_ERROR;
            $responseDto->msg = implode("\r\n", $error_list);
            return $responseDto;
        }
        $login_user_info = self::getCurrentLoginUserInfo();
        $current_uid = $login_user_info['id'];

        // 管理员没有部门，暂时不允许管理员添加或修改
        if (1 == $login_user_info['role_id']) {
            ErrorMsg::FillResponseAndLog($responseDto, ErrorMsg::ADMIN_NOT_ALLOWED);
            return $responseDto;
        }

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

            // 被分配给别人，不能再修改
            if ($login_user_info['is_clue_sale'] && $v_detail['post_sale_id'] > 0) { // && $v_detail['post_sale_id'] != $login_user_info['id']
                ErrorMsg::FillResponseAndLog($responseDto, ErrorMsg::NO_PRIVILEGE);
                return $responseDto;
            }
            // 只能修改分配给自己的
            if ($v_detail['post_sale_id'] > 0 && $v_detail['post_sale_id'] != $login_user_info['id']) {
                ErrorMsg::FillResponseAndLog($responseDto, ErrorMsg::NO_PRIVILEGE);
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
            // @2020.5.28 产品确认：主管不能创建线索，只能是员工创建
            if (User::LEVEL_STAFF != $login_user_info['level']) {
                ErrorMsg::FillResponseAndLog($responseDto, ErrorMsg::CUSTOMER_CLUE_CREATE_NOT_ALLOW_BY_ADMIN);
                return $responseDto;
            }
            $data_arr['creator_id'] = $current_uid;
            $data_arr['updator_id'] = $data_arr['creator_id'];
            $data_arr['created_time'] = $curr_datetime;
            if (!isset($data_arr['status'])) $data_arr['status'] = 1; // 新添加,默认都是启用
            //$data_arr['deleted_time'] = $this->theRepository::DATETIME_NOT_NULL_DEFAULT;
        }
        // 数据增加几个默认值
        $data_arr['updated_time'] = $curr_datetime;

        if (isset($request['id']) && $request['id']) {
            // 更新
            // 该条记录对应的线索clue_id需要变更为已处理 @2020.5.29 产品说，不管添加追踪记录、修改了信息也需要更新为已处理
            if (1 != $v_detail['opt_status'] && !$login_user_info['is_clue_sale']) $data_arr['opt_status'] = 1;
            $rlt = $this->theRepository->updateData($request['id'], $data_arr);
            /*if (isset($request['remark']) && $request['remark']) {
                // 插入一条追踪记录
                $data_arr['clue_id'] = $request['id'];
                unset($data_arr['id']);
                $this->customerClueTrackRepository->insertGetId($data_arr);
            }*/
            if (!$rlt) {
                ErrorMsg::FillResponseAndLog($responseDto, ErrorMsg::UPDATE_DB_FAILED);
                return $responseDto;
            }
        } else {
            // 添加的时候，如果客服没有备注，则备注信息自己填一下 @2020.5.15 产品确认
            if (!isset($data_arr['remark'])) $data_arr['remark'] = 'no remarks';

            if (!$login_user_info['is_clue_sale']) {
                // 售后员工添加，则该线索直接归属该员工
                $data_arr['post_sale_id'] = $current_uid;
                $data_arr['distribute_status'] = 1;         // 状态为已分配，自动分配给自己
            } else {
                $data_arr['post_sale_id'] = 0;  // 没有归属客服，属于待分配
                $data_arr['distribute_status'] = 0;
            }
            $data_arr['department_id'] = $login_user_info['department_id'];     // 所属部门ID

            $v_id = $this->insertClueAndTrack($data_arr, $request, $curr_datetime, $current_uid);
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
        $v_info = new CustomerClueDto();
        $v_detail = $this->addAttrName2Data($v_detail);
        $v_detail = $this->addCreatorName($v_detail);
        $v_detail = $this->addPostSaleIdName($v_detail);
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

    public function distributeOrNotList($request) {
        $responseDto = new ResponseDto();

        // 当前登录账号主管和员工看到的已处理、未处理不一样
        $login_user_info = self::getCurrentLoginUserInfo();
        if (!$login_user_info) {
            ErrorMsg::FillResponseAndLog($responseDto, ErrorMsg::UN_LOGIN);
            return $responseDto;
        }
        if (1 != $login_user_info['role_id']) {
            if (!$login_user_info || User::LEVEL_ADMIN != $login_user_info['level']) {
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
        }

        return $this->getList($request);
    }

    public function distributeNotList() {
        $request = request()->all();
        $request['post_sale_id'] = 0;
        $request['distribute_status'] = 0;
        $request['finish_status'] = 0;

        return $this->distributeOrNotList($request);
    }

    public function distributedList() {
        $request = request()->all();
        $request['post_sale_id'] = ['>', 0];
        $request['distribute_status'] = 1;
        $request['finish_status'] = 0;

        return $this->distributeOrNotList($request);
    }

    public function dealwithOrNotList($request) {
        $responseDto = new ResponseDto();

        // 当前登录账号主管和员工看到的已处理、未处理不一样
        $login_user_info = self::getCurrentLoginUserInfo();
        if (!$login_user_info) {
            ErrorMsg::FillResponseAndLog($responseDto, ErrorMsg::UN_LOGIN);
            return $responseDto;
        }
        // 必须是售后部门
        if (!$this->departmentRepository) $this->departmentRepository = new DepartmentRepository();
        $department_info = $this->departmentRepository->getInfoById($login_user_info['department_id']);

        if (1 != $login_user_info['role_id']) {
            if (2 != $department_info['job_type']) {
                // 只有售后部门才可以
                ErrorMsg::FillResponseAndLog($responseDto, ErrorMsg::DEPARTMENT_MUST_AFTER_SALE);
                return $responseDto;
            }

            /*if (User::LEVEL_STAFF == $login_user_info['level']) {
                if ($login_user_info['is_clue_sale']) {
                    //$request['post_sale_id'] = $login_user_info['id']; // 分配给当前用户的
                    $request['creator_id'] = $login_user_info['id']; // 线索客服创建的，应该能看到该线索
                } else {
                    $request['post_sale_id'] = $login_user_info['id']; // 分配给当前用户的
                }
            } else if (User::LEVEL_ADMIN == $login_user_info['level'])  {
                // 主管能看到自己部门下所有的线索，包括所有子部门
                $child_dept_list = parent::getChildrenDepartmentByDeptId($login_user_info['department_id']);
                if (!isset($request['department_id'])) $request['department_id'] = ['in', $child_dept_list];
            }*/
        }

        return $this->getList($request);
    }

    // 未处理线索是指已经分配出去，但是尚未处理的线索，不包含未分配的线索
    public function noDealwithList() {
        $request = request()->all();
        $request['post_sale_id'] = ['>', 0];    // 不包含未分配的线索
        $request['opt_status'] = 0;
        $request['finish_status'] = 0;
        return $this->dealwithOrNotList($request);
    }

    public function dealwithList() {
        $request = request()->all();
        $request['opt_status'] = 1;
        $request['finish_status'] = 0;
        return $this->dealwithOrNotList($request);
    }

    // 归档
    public function finished() {
        $request = request()->all();
        $request['opt_status'] = 1;
        $request['finish_status'] = 1;
        return $this->getList($request);
    }

    // 分配线索
    public function distribute() {
        $request = request()->all();
        $responseDto = new ResponseDto();

        $curr_datetime = date('Y-m-d H:i:s');
        // 参数校验数组, 当前登录用户是否有权限暂不验证，后面统一处理
        $rules = [
            'clue_ids' => 'required',
            'to_user_id' => 'required|integer|min:1',       // 要转移的客服
        ];
        $validate = Validator::make($request, $rules);
        if ($validate->fails()) {
            $error_list = $validate->errors()->all();
            $responseDto->status = ErrorMsg::PARAM_ERROR;
            $responseDto->msg = implode("\r\n", $error_list);
            return $responseDto;
        }

        $login_user_info = self::getCurrentLoginUserInfo();
        if (!$login_user_info || User::LEVEL_ADMIN != $login_user_info['level']) {
            // 只有主管才可以
            ErrorMsg::FillResponseAndLog($responseDto, ErrorMsg::ORDER_OPT_NEED_ADMIN);
            return $responseDto;
        }
        // 必须是售后部门
        if (!$this->departmentRepository) $this->departmentRepository = new DepartmentRepository();
        $department_info = $this->departmentRepository->getInfoById($login_user_info['department_id']);
        if (2 != $department_info['job_type']) {
            // 只有售后部门才可以
            ErrorMsg::FillResponseAndLog($responseDto, ErrorMsg::CUSTOMER_TRANSFER_ONLY_BY_AFTER_SALE);
            return $responseDto;
        }

        // 检查客服id是否存在
        $user_info = $this->userRepository->getUserById($request['to_user_id']);
        // 目标用户不存在
        if (!$user_info) {
            ErrorMsg::FillResponseAndLog($responseDto, ErrorMsg::USER_TO_NOT_EXISTS);
            return $responseDto;
        }
        // 目标用户所属部门必须是同部门
        if ($user_info['department_id'] != $login_user_info['department_id']) {
            ErrorMsg::FillResponseAndLog($responseDto, ErrorMsg::ADMIN_USER_DEPARTMENT_NOT_MATCH);
            return $responseDto;
        }

        // 不能分配给线索客服
        if ($user_info['is_clue_sale']) {
            ErrorMsg::FillResponseAndLog($responseDto, ErrorMsg::CUSTOMER_CLUE_CANNOT_DISTRIBUTE_TO_CLUE_SALE);
            return $responseDto;
        }

        // 更新, 直接写sql，将原客服id换成新客服id，where条件要写好
        $rlt = $this->afterSaleCustomerClueDistribute($request, $curr_datetime, $login_user_info);
        if (!$rlt) {
            ErrorMsg::FillResponseAndLog($responseDto, ErrorMsg::UPDATE_DB_FAILED);
            return $responseDto;
        }
        return $responseDto;
    }

    // 更新数据
    private function afterSaleCustomerClueDistribute($request, $created_time, $login_user_info) {
        $creator_id = $login_user_info['id'];

        // 更新, 直接写sql，将原客服id换成新客服id，where条件要写好
        $sql_where = [
            'id' => ['in', $request['clue_ids']],
            'post_sale_id' => 0,
        ];
        $data_arr = [
            'post_sale_id' => $request['to_user_id'],
            'distribute_status' => 1                    // 状态为已分配
        ];

        // 需要插入分配记录
        if (!$this->customerClueDistributeRepository) $this->customerClueDistributeRepository = new CustomerClueDistributeRepository();
        $distribute_data = [];
        foreach ($request['clue_ids'] as $clue_id) {
            $one_distribute = [
                'part' => $creator_id % 10,
                'distribute_user_id' => $creator_id,
                'pre_distribute_id' => 0,
                'department_id' => $login_user_info['department_id'],
                'clue_id' => $clue_id,
                'distributed_user_id' => $request['to_user_id'],
                'status' => 1,                                          // 默认启用
                'distributed_time' => $created_time,
                'created_time' => $created_time,
                'creator_id' => $creator_id,
                'updator_id' => $creator_id,
            ];
            $distribute_data[] = $one_distribute;
        }

        // 事务处理
        \DB::beginTransaction();
        try {
            $this->theRepository->updateMultiByCondition($sql_where, $data_arr);
            // 需要插入多条分配记录
            $this->customerClueDistributeRepository->insertMultiple($distribute_data);
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

    // 插入线索和第一条追踪记录，需要进行事务处理
    public function insertClueAndTrack($data_arr, $request, $create_time='', $creator_id=0) {

        \DB::beginTransaction();

        try {
            $clue_id = $this->theRepository->insertGetId($data_arr);
            if (!$clue_id) return false;
            // 再添加一条追踪记录
            $this->customerClueTrackRepository->insertTrackByClueId($clue_id, $data_arr, $create_time, $creator_id);
            \DB::commit();
        } catch (\Exception $e) {
            $msg = 'db-Transaction-error: insert, ' . $e->getMessage() . ' data:';
            \Log::error($msg, $request);
            \DB::rollBack();
            return false;
        }
        return $clue_id;
    }

    public function getAbleClue() {
        $request = request()->all();
        $responseDto = new ResponseDto();
        $request[$responseDto::WITHOUT_ORDER_STATS] = 1;    // 不需要统计数据

        // 状态为：已处理、未归档
        $request['finish_status'] = 0;  // 未成交（未归档）
        $request['opt_status'] = 1;     // 已处理

        // 返回有权限的线索
        $login_user_info = self::getCurrentLoginUserInfo();
        if (1 != $login_user_info['role_id']) {
            // 当前用户如果是员工，则只返回自己
            /*if (User::LEVEL_STAFF == $login_user_info['level']) {
                $request['post_sale_id'] = $login_user_info['id'];
            } else {
                // 主管的话，找到主管所属部门，此部门下的所有员工，包括各级子部门的员工，先查所有部门
                $all_departments = $this->departmentRepository->getAllDepartment();
                // 查询到部门下的所有子部门
                $staff_dept_list = \getAllChildIdByParentId($all_departments, $login_user_info['department_id']);
                if ($staff_dept_list) {
                    $staff_dept_list = array_column($staff_dept_list, 'id');
                }
                $staff_dept_list[] = $login_user_info['department_id']; // 加上本身所在部门
                $request['department_id'] = ['in', $staff_dept_list];
            }*/
        }
        return $this->getList($request);
    }
}
