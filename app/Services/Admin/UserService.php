<?php

namespace App\Services\Admin;

use App\Dto\DataListDto;
use App\Dto\ResponseDto;
use App\Dto\TokenDto;
use App\Dto\UserDto;
use App\Dto\UserWeightDto;
use App\Jobs\SendEmail;
use App\Libs\Utils\ErrorMsg;
use App\Mappers\RouteMapper;
use App\Models\Admin\SysOptRecord;
use App\Models\Admin\User;
use App\Repositories\Admin\CallUserConfigRepository;
use App\Repositories\Admin\DepartmentRepository;
use App\Repositories\Admin\DepartmentWeightRepository;
use App\Repositories\Admin\RolePrivilegeRepository;
use App\Repositories\Admin\RoleRepository;
use App\Repositories\Admin\SysConfigRepository;
use App\Repositories\Admin\SysOptRecordRepository;
use App\Repositories\Admin\UserLoginRecordRepository;
use App\Repositories\Admin\UserRepository;
use App\Repositories\Admin\UserWeightRepository;
use App\Services\BaseService;
use Auth;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class UserService extends BaseService
{
    protected $userRepository;
    protected $userLoginRecordRepository;
    protected $sysOptRecordRepository;
    protected $roleRepository;
    protected $rolePrivilegeRepository;
    protected $userWeightRepository;
    protected $departmentRepository;
    protected $departmentWeightRepository;
    protected $callUserConfigRepository;

    public function __construct() {
        $this->userRepository = new UserRepository();
        $this->userLoginRecordRepository = new UserLoginRecordRepository();
        $this->sysOptRecordRepository = new SysOptRecordRepository();
        $this->userWeightRepository = new UserWeightRepository();
        $this->departmentRepository = new DepartmentRepository();
    }

    // 验证规则
    public function getUserAllRules() {
        $all_rules = [
            'uid' => 'required|string|min:1',

            'email' => 'required|email|between:4,60',
            'password' => 'required|string|min:32', // 前端md5之后传过来。

            'old_password' => 'required|string',
            'new_password' => 'required|confirmed|min:8',

            'vcode' => 'required|string|min:1',        // 错误超过次数，需要提供图片验证码参数
            'vcode_key' => 'required|string|min:10',

            'access_token' => 'string|min:1',

            // 员工添加
            'real_name' => 'required|string|min:1',
            'phone' => 'required|string|min:6',


        ];
        return $all_rules;
    }

    // 自定义登录验证
    public function loginAdmin() {
        $request = request()->all();
        $responseDto = new ResponseDto();

        // 参数校验数组
        $rules = self::getValidatorRules(['email', 'password']);
        // 获取该账户错误尝试的次数
        if (isset($request['email']) && $this->isOverThrottleByEmail($request['email'])) {
            $rules = array_merge($rules, self::getValidatorRules(['vcode', 'vcode_key']));
        }
        // email、密码参数校验
        $validate = Validator::make($request, $rules);
        if ($validate->fails()) {
            $error_list = $validate->errors()->all();
            $responseDto->status = ErrorMsg::PARAM_ERROR;
            $responseDto->msg = implode("\r\n", $error_list);
            return $responseDto;
        }
        // 首先验证图片验证码，是否正确；因为不需要查询数据库
        if (isset($rules['vcode'])) {
            $check_result = app('captcha')->check_api($request['vcode'], $request['vcode_key']); // bool:true;false
            if (!$check_result) {
                ErrorMsg::FillResponseAndLog($responseDto, ErrorMsg::AUTHCODE_ERROR);
                return $responseDto;
            }
        }

        // 检查email是否存在
        // $user_detail = $this->userRepository->getByEmail($request['email']);
        $user_detail = User::where('email', $request['email'])->first();
        if(is_null($user_detail)){
            throw new ModelNotFoundException('account not found');
        }
        $user_detail->append(['receive_status', 'is_super', 'job_type']);
        $user_detail = $user_detail->toArray();

        if (!$user_detail || !isset($user_detail['status'])) {
            ErrorMsg::FillResponseAndLog($responseDto, ErrorMsg::USER_NOT_EXISTS);
            return $responseDto;
        }
        if (User::ORDER_STATUS_DELETE == $user_detail['status']) {
            ErrorMsg::FillResponseAndLog($responseDto, ErrorMsg::USER_DELETED);
            return $responseDto;
        }
        if (User::ORDER_STATUS_CLOSE == $user_detail['status']) {
            ErrorMsg::FillResponseAndLog($responseDto, ErrorMsg::USER_STATUS_CLOSE);
            return $responseDto;
        }

        // 用户名和密码是否正确
        $credentials = [
            'email'    => $request['email'],
            'password' => $request['password'],
        ];
        $token = auth('api')->attempt($credentials);
        if (!$token) {
            // 密码错误，顺便记录密码错误次数
            $error_num = $this->addPasswordErrorNumByEmail($request['email']);

            // 再次检查是否超限
            $is_over = $this->isOverThrottleByEmail($request['email']);

            // 返回的数据
            $data = [
                'login_failed_count' => $error_num,
                'verify_code_next' => $is_over, // 错误3次还需要返回图片验证码，并进行图片验证的校验
            ];
            $responseDto->data = $data;
            ErrorMsg::FillResponseAndLog($responseDto, ErrorMsg::PASSWORD_ERROR);
            return $responseDto;
        }
        $token_obj = $this->getTokenObj($token, isset($request['with_parse']) && $request['with_parse']); // token数据

        $list = [$user_detail];
        $list = self::getUserWithWeight($list);
        $user_detail = $list[0];

        // 登录成功，返回用户信息和token数据
        $user_info = new UserDto();
        $user_info->Assign($this->addAttrName2Data($user_detail));
        $user_info->token = $token_obj->token;
        $user_info->token_type = $token_obj->token_type;
        $user_info->expires_in = $token_obj->expires_in;
        if (property_exists($token_obj, 'parse_info')) $user_info->parse_info = $token_obj->parse_info;
        $responseDto->data = $user_info;

        // 记录一下用户登录记录
        $data_arr = [
            'user_id' => $user_detail['id'],
            'user_ip' => request()->getClientIp(),
            'token' => $token,
            'login_time' => date('Y-m-d H:i:s'), // 不赋值数据库会自动维护该字段
        ];
        try{
            $this->userLoginRecordRepository->insertGetId($data_arr);
        } catch (\Exception $e) {
            Log::error('userLoginRecordRepository insertGetId failed!');
        }

        return $responseDto;
    }

    public function logout() {
        $responseDto = new ResponseDto();

        // 退出的时候，停止接单
        $request = [];
        $request['id'] = auth('api')->id(); // 当前用户
        $request['order_status'] = User::ORDER_STATUS_CLOSE;
        $request['order_failure_time'] = $this->userRepository::DATETIME_NOT_NULL_DEFAULT;
        self::addOrUpdate($request); // 更新接单状态字段，更新代码里面顺便删除了缓存

        auth('api')->logout();
        return $responseDto;
    }

    public function refresh() {
        $request = request()->all();
        $responseDto = new ResponseDto();

        $token = auth('api')->refresh(); // 更新后，返回新的token
        if (!$token) {
            ErrorMsg::FillResponseAndLog($responseDto, ErrorMsg::UNKNOWN_ERROR);
            return $responseDto;
        }

        $responseDto->data = self::getTokenObj($token, isset($request['with_parse']) && $request['with_parse']);
        return $responseDto;
    }

    protected function getTokenObj($token, $with_parse = true) {
        $responseDto = new TokenDto();
        $responseDto->token = $token;
        $responseDto->expires_in = auth('api')->factory()->getTTL() * 60;
        $responseDto->expires_at = ''; // 过期时间

        $parse_info = $this->userRepository->parseJwtToken($token);
        Log::info("JWT-token-parse-data: \r\n" . print_r($parse_info, true));
        $responseDto->expires_at = $parse_info->exp;

        if ($with_parse) $responseDto->parse_info = $parse_info;

        return $responseDto;
    }

    // 校验某email的密码错误次数是否超限，超过3次就需要提供正确的验证码
    public function addPasswordErrorNumByEmail($email) {
        return $this->userRepository->addPasswordErrorNumByEmail($email);
    }
    public function isOverThrottleByEmail($email) {
        return $this->userRepository->isOverThrottleByEmail($email);
    }

    public function getById($id) {
        $request['uid'] = $id;
        $responseDto = new ResponseDto();

        $login_user_info = self::getCurrentLoginUserInfo(); // 当前登录用户
        // TODO 是否有权限查看，需要进行权限检查 ???? 后面统一进行权限检查

        // uid参数校验
        $validate = Validator::make($request, self::getValidatorRules(['uid']));
        if ($validate->fails()) {
            $error_list = $validate->errors()->all();
            $responseDto->status = ErrorMsg::PARAM_ERROR;
            $responseDto->msg = implode("\r\n", $error_list);
            return $responseDto;
        }

        $user_detail = $this->userRepository->getUserById($request['uid']);
        if (!$user_detail) {
            ErrorMsg::FillResponseAndLog($responseDto, ErrorMsg::USER_NOT_EXISTS);
            return $responseDto;
        }
        $list = [$user_detail];
        $list = self::getUserWithWeight($list);
        $user_detail = $list[0];

        // 成功，返回用户信息
        $user_info = new UserDto();
        $user_info->Assign($this->addAttrName2Data($user_detail));
        $responseDto->data = $user_info;

        return $responseDto;
    }

    public function me() {
        $responseDto = new ResponseDto();

        // $user_detail = self::getCurrentLoginUserInfo();
        // $user_detail['receive_status'] = Auth('api')->user()->receive_status;
        $user_detail = Auth('api')->user();
        $user_detail->append(['receive_status', 'is_super']);
        $user_detail = $user_detail->toArray();

        if (!$user_detail) {
            ErrorMsg::FillResponseAndLog($responseDto, ErrorMsg::USER_NOT_EXISTS);
            return $responseDto;
        }

        $list = [$user_detail];
        $list = self::getUserWithWeight($list);
        $user_detail = $list[0];

        // 返回用户信息
        $user_info = new UserDto();
        $user_info->Assign($this->addAttrName2Data($user_detail));
        $responseDto->data = $user_info;

        return $responseDto;
    }

    // 校验规则
    public function getValidatorRules(array $field_list) {
        $all_rules = self::getUserAllRules();

        $rules = [];
        if (!$field_list)
            return $rules;
        foreach ($field_list as $field) {
            if (array_key_exists($field, $all_rules))
            $rules[$field] = $all_rules[$field];
        }
        return $rules;
    }

    public function parseJwt() {
        $request = request()->all();
        $responseDto = new ResponseDto();

        if (!isset($request['access_token']) || !$request['access_token'] || false === strpos($request['access_token'], '.')) {
            ErrorMsg::FillResponseAndLog($responseDto, ErrorMsg::PARAM_ERROR);
            return $responseDto;
        }
        $token = $request['access_token'];
        $responseDto->access_token_orig = $token;

        $parse_info = $this->userRepository->parseJwtToken($token);
        //Log::info("JWT-token-parse-data: \r\n" . print_r($parse_info, true));
        $responseDto->data = $parse_info;

        return $responseDto;
    }

    // 修改密码
    public function setPassword() {
        $request = request()->all();
        $responseDto = new ResponseDto();

        // 参数校验数组
        $rules = self::getValidatorRules(['old_password', 'new_password', 'new_password_confirmation']);
        $validate = Validator::make($request, $rules);
        if ($validate->fails()) {
            $error_list = $validate->errors()->all();
            $responseDto->status = ErrorMsg::PARAM_ERROR;
            $responseDto->msg = implode("\r\n", $error_list);
            return $responseDto;
        }

        // 获取当前用户信息
        $user_id = auth('api')->id();
        $user_detail = $this->userRepository->getUserById($user_id, true);
        if (!$user_detail || !isset($user_detail['password'])) {
            ErrorMsg::FillResponseAndLog($responseDto, ErrorMsg::USER_NOT_EXISTS);
            return $responseDto;
        }
        // 检查旧密码是否正确
        if (!Hash::check($request['old_password'], $user_detail['password'])) {
            ErrorMsg::FillResponseAndLog($responseDto, ErrorMsg::OLD_PASSWORD_ERROR);
            return $responseDto;
        }
        // 进行密码设置
        $result = $this->userRepository->setPasswordById($user_detail['id'], Hash::make($request['new_password']));
        if (!$result) {
            ErrorMsg::FillResponseAndLog($responseDto, ErrorMsg::SET_PASSWORD_ERROR);
            return $responseDto;
        }

        return $responseDto;
    }

    // 新增或修改信息
    public function addOrUpdate($request=null) {
        if(!$request) $request = request()->all();
        $responseDto = new ResponseDto();

        if ('cli' != php_sapi_name()) $current_uid = auth('api')->id();
        else $current_uid = ($request['creator_id'] ?? 0) + 0;

        // 验证当前登录用户, TODO 是否有权限
        $user_current = self::getCurrentLoginUserInfo();
        if (!$user_current || !isset($user_current['id'])) {
            ErrorMsg::FillResponseAndLog($responseDto, ErrorMsg::USER_NOT_EXISTS);
            return $responseDto;
        }
        if (!isset($request['is_clue_sale'])) $request['is_clue_sale'] = 0; // 默认值

        $curr_datetime = date('Y-m-d H:i:s');
        $data_arr = $request;
        if (isset($request['id']) && $request['id']) {
            // 修改的情况
            $data_arr['id'] = $request['id'];
            // 检查用户是否存在
            $user_detail = $this->userRepository->getUserById($request['id']);
            if (!$user_detail) {
                ErrorMsg::FillResponseAndLog($responseDto, ErrorMsg::USER_NOT_EXISTS);
                return $responseDto;
            }

            // unset($data_arr['email']);  // 邮箱也可修改, 管理员可以修改员工的密码
            // 管理员可以修改员工的密码
            $rules = [
                'email' => 'sometimes|email|between:4,60',
                'password' => 'sometimes|string|between:6,16'
            ];
            if (isset($request['role_id'])) $rules['role_id'] = 'required|integer|min:1';
            $validate = Validator::make($request, $rules);
            if ($validate->fails()) {
                $error_list = $validate->errors()->all();
                $responseDto->status = ErrorMsg::PARAM_ERROR;
                $responseDto->msg = implode("\r\n", $error_list);
                return $responseDto;
            }
            // 如果角色有修改，需要检查是否售后部门、是否员工、新角色是否线索客服
            if (isset($request['role_id'])) {
                $l_level = $request['level'] ?? $user_detail['level'];
                $l_depart = $request['department_id'] ?? $user_detail['department_id'];
                $l_depart_info = $this->departmentRepository->getInfoById($l_depart);
                if (2 == $l_depart_info['job_type'] && User::LEVEL_STAFF == $l_level) {
                    // 售后部门，并且是员工，检查该角色的权限
                    $l_user_info = [
                        'level' => $l_level,
                        'role_id' => $request['role_id'],
                    ];
                    $is_clue_sale = $this->checkIsClueSale($l_user_info);
                    if ($is_clue_sale) $data_arr['is_clue_sale'] = 1;       // 设定为线索客服
                }
            }

            $data_arr['updator_id'] = $current_uid;
            $data_arr['updated_time'] = $curr_datetime;
            $data_arr['deleted_time'] = $data_arr['deleted_time'] ?? $this->userRepository::DATETIME_NOT_NULL_DEFAULT;
        } else {
            // 新增, 参数校验数组
            $rules = self::getValidatorRules(['email', 'real_name']);
            $rules['password'] = 'required|string|confirmed|between:6,16';
            //$rules['department_id'] = 'required|integer|min:1';
            $rules['level'] = 'required|integer|min:1';
            $rules['role_id'] = 'required|integer|min:1';

            $validate = Validator::make($request, $rules);
            if ($validate->fails()) {
                $error_list = $validate->errors()->all();
                $responseDto->status = ErrorMsg::PARAM_ERROR;
                $responseDto->msg = implode("\r\n", $error_list);
                return $responseDto;
            }

            if (!isset($request['department_id']) || $request['department_id'] <= 0) {
                ErrorMsg::FillResponseAndLog($responseDto, ErrorMsg::DEPARTMENT_NEEDED);
                return $responseDto;
            }
            // 需要检查是否售后部门、是否员工、是否线索客服
            $l_depart_info = $this->departmentRepository->getInfoById($request['department_id']);
            if (2 == $l_depart_info['job_type'] && User::LEVEL_STAFF == $request['level']) {
                // 售后部门，并且是员工，
                $l_user_info = [
                    'level' => $request['level'],
                    'role_id' => $request['role_id'],
                ];
                $is_clue_sale = $this->checkIsClueSale($l_user_info);
                if ($is_clue_sale) $data_arr['is_clue_sale'] = 1;       // 设定为线索客服
            }

            if (!$this->departmentWeightRepository) $this->departmentWeightRepository = new DepartmentWeightRepository();
            $department_weight_info = $this->departmentWeightRepository->getDeptWeightByDeptId($request['department_id']);

            // 检查email是否存在
            $user_detail = $this->userRepository->getByEmail($request['email']);
            if ($user_detail) {
                ErrorMsg::FillResponseAndLog($responseDto, ErrorMsg::EMAIL_EXISTS);
                return $responseDto;
            }

            // 设置初始密码
            $data_arr['password'] = Hash::make(md5($data_arr['password']));
            $data_arr['creator_id'] = $current_uid;
            $data_arr['updator_id'] = $data_arr['creator_id'];
            $data_arr['created_time'] = $curr_datetime;
            $data_arr['updated_time'] = $data_arr['created_time'];
            $data_arr['deleted_time'] = $this->userRepository::DATETIME_NOT_NULL_DEFAULT;


            // 数据增加几个默认值
            //$data_arr['department_id'] = $request['department_id'] ?? 0;
            //$data_arr['level'] = $request['level'] ?? 1;
            $data_arr['status'] = $request['status'] ?? 1;
            $data_arr['remember_token'] = $request['remember_token'] ?? '';
            $data_arr['last_login_ip'] = ''; // request()->getClientIp();
            $data_arr['last_login_time'] = $this->userRepository::DATETIME_NOT_NULL_DEFAULT;
        }
        if (isset($request['id']) && $request['id']) {
            // 管理员可以修改员工的密码
            if (isset($data_arr['password']) && $data_arr['password']) {
                $data_arr['password'] = Hash::make(md5($data_arr['password']));
            }

            // 更新
            $rlt = $this->updateUserAndAttrData($data_arr, $request, $curr_datetime, $current_uid);
            if (!$rlt) {
                ErrorMsg::FillResponseAndLog($responseDto, ErrorMsg::UPDATE_DB_FAILED);
                return $responseDto;
            }

            // 管理员可以修改员工的密码，将初始密码通过邮箱发送给员工邮箱, 放到队列中，进行异步处理 2020.04.17产品确认不用发邮件通知
            //if (isset($data_arr['password']) && $data_arr['password']) {
            //    if (!isset($request['email']) || !$request['email']) $request['email'] = $user_detail['email'];
            //    dispatch(new SendEmail($request));
            //}

            $opt_type = SysOptRecord::TYPE_EDIT;
            $opt_title = '修改员工信息';
            $opt_content = $current_uid . ' edit user info for user-id: ' . $request['id'];
        } else {
            if (!isset($data_arr['status'])) $data_arr['status'] = 1;    // 默认启用

            $user_id = $this->insertUserAndAttrData($data_arr, $request, $curr_datetime, $current_uid, $department_weight_info);
            if (!$user_id) {
                ErrorMsg::FillResponseAndLog($responseDto, ErrorMsg::INSERT_DB_FAILED);
                return $responseDto;
            }
            $data_arr['id'] = $user_id; // 产生的用户ID

            // 将初始密码通过邮箱发送给员工邮箱, 放到队列中，进行异步处理
            dispatch(new SendEmail($request));

            $opt_type = SysOptRecord::TYPE_CREATE;
            $opt_title = '添加员工';
            $opt_content = $current_uid . ' create new staff for user-id: ' . $user_id;
        }
        // 通知Role，修改一下角色Role-id下员工数量
        $this->notifyRoleUpUserNumCache($data_arr);
        $this->notifyDepartmentUserNumCache($data_arr);
        $this->notifyUserAllCache($data_arr);

        // 记录一下系统操作日志
        $data_arr = [
            'user_id' => $current_uid,
            'user_ip' => request()->getClientIp(),
            'type' => $opt_type,
            'module' => SysOptRecord::MODULE_USER,
            'title' => $opt_title,
            'req_uri' => request()->getUri(),
            'req_content' => $opt_content,
            'opt_time' => $curr_datetime,
        ];
        try{
            $this->sysOptRecordRepository->insertGetId($data_arr);
        } catch (\Exception $e) {
            Log::error('sysOptRecordRepository insertGetId failed! ' . $e->getMessage());
        }

        return $responseDto;
    }

    // 插入用户信息和属性，员工的语言、角色数据放在另一张表，需要进行事务处理
    public function insertUserAndAttrData($data_arr, $request, $create_time='', $creator_id=0, $department_weight_info=[]) {
        if (!$this->callUserConfigRepository) $this->callUserConfigRepository = new CallUserConfigRepository();
        \DB::beginTransaction();
        try {
            $user_id = $this->userRepository->insertGetId($data_arr);
            if (!$user_id)
                return false;
            $this->userRepository->insertUserAttrMultiByUid($user_id, $request, $create_time, $creator_id);
            $this->userWeightRepository->insertUserWeightMultiByUserId($user_id, $request, $create_time, $creator_id); // 权重
            $this->callUserConfigRepository->insertMultipleCallUserConfigByCountryId($user_id, $department_weight_info, $create_time, $creator_id); // 权重
            \DB::commit();
        } catch (\Exception $e) {
            $msg = 'db-Transaction-error: table, ' . $this->userRepository->getModel()->getTable() . ', ' .
                $this->userRepository->getUserAttrModel()->getTable() . ' error: ' . $e->getMessage() . ' data:';
            Log::error($msg, $request);
            \DB::rollBack();
            return false;
        }
        return $user_id;
    }

    // 更新用户信息和属性，员工的语言、角色数据放在另一张表，需要进行事务处理
    public function updateUserAndAttrData($data_arr, $request, $create_time='', $creator_id=0) {
        \DB::beginTransaction();
        try{
            $this->userWeightRepository->updateUserWeightMultiByUserId($request['id'], $data_arr, $create_time, $creator_id);
            $this->userRepository->updateUserAttrMultiByUid($request['id'], $data_arr, $create_time, $creator_id); // 优先更新用户属性表
            $this->userRepository->updateData($request['id'], $data_arr);
            \DB::commit();
        } catch (\Exception $e) {
            $msg = 'db-Transaction-error: table, ' . $this->userRepository->getModel()->getTable() . ', ' .
                $this->userRepository->getUserAttrModel()->getTable() . ' error: ' . $e->getMessage() . ' data:';
            Log::error($msg, $request);
            \DB::rollBack();
            return false;
        }
        return true;
    }

    // 员工列表，有权限查看的全部员工列表，如果是主管，能看到所有下级
    public function getList() {
        $request = request()->all();
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
        $login_user_info = self::getCurrentLoginUserInfo(); // 当前登录用户
        if (!$login_user_info) {
            ErrorMsg::FillResponseAndLog($responseDto, ErrorMsg::USER_NOT_EXISTS);
            return $responseDto;
        }

        // 用户所属部门的类型（售前售后）
        //$department_info = $this->departmentRepository->getInfoById($login_user_info['department_id']);
        //$department_info['job_type'];

        if (1 != $login_user_info['role_id']) {
            // 当前用户如果是员工，则只返回自己
            if (User::LEVEL_STAFF == $login_user_info['level']) {
                $request['id'] = $login_user_info['id'];
            } else {
                // 主管的话，找到主管所属部门，
                // 此部门下的所有员工，包括各级子部门的员工，先查所有部门
                $all_departments = $this->departmentRepository->getAllDepartment();
                // 查询到部门下的所有子部门
                $staff_dept_list = \getAllChildIdByParentId($all_departments, $login_user_info['department_id']);
                if ($staff_dept_list) {
                    $staff_dept_list = array_column($staff_dept_list, 'id');
                }
                $staff_dept_list[] = $login_user_info['department_id']; // 加上本身所在部门
                $request['department_id'] = ['in', $staff_dept_list];
            }
        }

        // 获取数据，包含总数字段
        $list = $this->userRepository->getList($request);
        if (!$list || !isset($list[$responseDto::DTO_FIELD_TOTOAL]) || !isset($list[$responseDto::DTO_FIELD_LIST])) {
            ErrorMsg::FillResponseAndLog($responseDto, ErrorMsg::DATA_EMPTY);
            return $responseDto;
        }
        if ($list[$responseDto::DTO_FIELD_LIST]) {
            // 获取用户id列表
            $uid_list = [];
            foreach ($list[$responseDto::DTO_FIELD_LIST] as $key => $user_detail) {
                $uid_list[] = $user_detail['id'];
            }
            $user_attr_list = $this->userRepository->getUserAttrByUserIdList($uid_list);

            // 分单比例和权重设置
            $list[$responseDto::DTO_FIELD_LIST] = self::getUserWithWeight($list[$responseDto::DTO_FIELD_LIST]);

            // 成功，返回用户信息
            foreach ($list[$responseDto::DTO_FIELD_LIST] as $key => $user_detail) {
                $user_info = new UserDto();
                if (isset($user_attr_list[$user_detail['id']]) && isset($user_attr_list[$user_detail['id']]['role_id'])) {
                    // 当前只有角色id单独放到user_attr表，其他属性需要的时候再补充
                    $user_detail['role_id'] = $user_attr_list[$user_detail['id']]['role_id'];
                }
                $user_info->Assign($this->addAttrName2Data($user_detail));
                $list[$responseDto::DTO_FIELD_LIST][$key] = $user_info;
            }
        }

        $data_list = new DataListDto();
        $data_list->Assign($list);
        $responseDto->data = $data_list;
        return $responseDto;
    }

    // TODO 页面操作事件通知接口
    public function pageAction() {
        $responseDto = new ResponseDto();
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

    // 通知Role进行缓存更新
    public function notifyRoleUpUserNumCache($data_arr) {
        $this->roleRepository = new RoleRepository();
        $this->roleRepository->updateUserNumCache($data_arr);
    }
    public function notifyDepartmentUserNumCache($data_arr) {
        $this->userRepository->updateDepartmentUserNumCache($data_arr);
    }
    public function notifyUserAllCache($data_arr) {
        $this->userRepository->updateUserAllCache($data_arr);
    }

    // 拼装员工在各个语言的权重数据
    public function getUserWithWeight($orig_user_list) {
        // 也可放到逻辑层，因为需要调用不同的service；// TODO 需要优化，因为数据量可能大
        // 员工还需要联合权重表，一个员工可能对应多个语言
        $l_weight_repo = new UserWeightRepository();
        $l_weight_all = $l_weight_repo->getAllUserWeight([0, 1]); // 只需要启用、停用状态
        if ($l_weight_all) {
            // 按照用户id归类
            $l_weight_list = [];
            foreach ($l_weight_all as $row) {
                $l_weight_list[$row['user_id']][] = $row;
            }
        }

        foreach ($orig_user_list as $key => &$row) {
            if (!isset($l_weight_list[$row['id']]))
                continue;

            // 每个部门都必须有存在都国家和权重，否则不返回？可以返回，便于排查问题
            foreach ($l_weight_list[$row['id']] as $v_detail) {
                $v_info = new UserWeightDto();
                $v_info->Assign($this->addLanguageName($v_detail));
                $row['language_weight_ratio'][] = $v_info; // 加一个字段

                if (!isset($row['language_ids']))
                    $row['language_ids'] = '';
                $row['language_ids'] .= $v_detail['language_id'] . ',';
            }
            $row['language_ids'] = rtrim($row['language_ids'], ' ,');
        }
        return $orig_user_list;
    }

    // 接单设置：开始接单/停止接单
    public function receiveOrder() {
        $request = request()->only('order_status');
        $request['id'] = auth('api')->id(); // 当前用户
        $responseDto = new ResponseDto();

        // 参数校验数组, 当前登录用户是否有权限暂不验证，后面统一处理
        $rules = [
            'order_status' => 'required|integer'
        ];
        $validate = Validator::make($request, $rules);
        if ($validate->fails()) {
            $error_list = $validate->errors()->all();
            $responseDto->status = ErrorMsg::PARAM_ERROR;
            $responseDto->msg = implode("\r\n", $error_list);
            return $responseDto;
        }
        if ($request['order_status']) $request['order_status'] = User::ORDER_STATUS_OPNE;
        else {
            $request['order_status'] = User::ORDER_STATUS_CLOSE;
            $request['order_failure_time'] = $this->userRepository::DATETIME_NOT_NULL_DEFAULT;
        }
        self::addOrUpdate($request); // 更新接单状态字段，更新代码里面顺便删除了缓存

        if (User::ORDER_STATUS_OPNE == $request['order_status']) {
            // 顺便设置接单失效时间
            self::setReceiveOrderExpireTime();
        }

        return $responseDto;
    }

    // 更新接单过期时间，调用每个接口都需要更新此字段数据
    public function setReceiveOrderExpireTime() {
        if ('cli' == php_sapi_name()) return 1;   // 非web请求，不修改

        $current_uid = auth('api')->id();
        if (!$current_uid) return 1;   // 未登录用户也不用处理

        $user_detail = $this->userRepository->getUserById($current_uid);
        if (!$user_detail) return 1;

        // 获取系统超时配置
        $v_detail = (new SysConfigRepository())->getInfoById(1);
        if (!$v_detail) {
            // 返回默认配置：
            $v_detail = ['value_1' => '20'];
        }
        if ($v_detail['value_1'] <= 0) return 1;

        // 设置过期时间
        $request['id'] = $current_uid;
        $request['order_failure_time'] = date('Y-m-d H:i:s', time() + $v_detail['value_1'] * 60);
        // TODO 为了不频繁user缓存数据，后面可以优化
        self::addOrUpdate($request);

        return 1;
    }

    // 设置语言
    public function changeWebLanguage() {
        $request = request()->only('language_id');
        $request['id'] = auth('api')->id(); // 当前用户
        $responseDto = new ResponseDto();

        // 参数校验数组, 当前登录用户是否有权限暂不验证，后面统一处理
        $rules = [
            'language_id' => 'required|integer|min:1'
        ];
        $validate = Validator::make($request, $rules);
        if ($validate->fails()) {
            $error_list = $validate->errors()->all();
            $responseDto->status = ErrorMsg::PARAM_ERROR;
            $responseDto->msg = implode("\r\n", $error_list);
            return $responseDto;
        }
        // 当前只有英文或简体中文
        if (22 == $request['language_id']) $request['web_language'] = 'zh_CN';
        else $request['web_language'] = 'en_US';
        self::addOrUpdate($request); // 用户网页默认语言，更新代码里面顺便删除了缓存

        return $responseDto;
    }

    // 我的下属员工，TODO 先返回直接下属
    public function getMySubordinate() {
        $request = request()->all();
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
        $login_user_info = self::getCurrentLoginUserInfo(); // 当前登录用户
        if (!$login_user_info) {
            ErrorMsg::FillResponseAndLog($responseDto, ErrorMsg::USER_NOT_EXISTS);
            return $responseDto;
        }

        if (1 != $login_user_info['role_id']) {
            // 当前用户如果是员工，则只返回自己
            if (User::LEVEL_STAFF == $login_user_info['level']) {
                $request['id'] = $login_user_info['id'];
            } else {
                // 主管的话，找到主管所属部门，
                //    只需要直接下属，不需要包括各级子部门的员工，售前未分配订单分配客服使用此接口
                $request['department_id'] = $login_user_info['department_id'];
            }
        }

        $request['level'] = User::LEVEL_STAFF; // 只需要返回员工，不返回主管

        // 获取数据，包含总数字段
        $list = $this->userRepository->getList($request);
        if (!$list || !isset($list[$responseDto::DTO_FIELD_TOTOAL]) || !isset($list[$responseDto::DTO_FIELD_LIST])) {
            ErrorMsg::FillResponseAndLog($responseDto, ErrorMsg::DATA_EMPTY);
            return $responseDto;
        }
        if ($list[$responseDto::DTO_FIELD_LIST]) {
            // 获取用户id列表
            $uid_list = [];
            foreach ($list[$responseDto::DTO_FIELD_LIST] as $key => $user_detail) {
                $uid_list[] = $user_detail['id'];
            }
            $user_attr_list = $this->userRepository->getUserAttrByUserIdList($uid_list);

            // 分单比例和权重设置
            $list[$responseDto::DTO_FIELD_LIST] = self::getUserWithWeight($list[$responseDto::DTO_FIELD_LIST]);

            // 成功，返回用户信息
            foreach ($list[$responseDto::DTO_FIELD_LIST] as $key => $user_detail) {
                $user_detail['audit_not_num'] =  RouteMapper::getModel('audit_not', 1, $user_detail['id']) ->count();

                $user_info = new UserDto();
                if (isset($user_attr_list[$user_detail['id']]) && isset($user_attr_list[$user_detail['id']]['role_id'])) {
                    // 当前只有角色id单独放到user_attr表，其他属性需要的时候再补充
                    $user_detail['role_id'] = $user_attr_list[$user_detail['id']]['role_id'];
                }
                $user_info->Assign($this->addAttrName2Data($user_detail));
                $list[$responseDto::DTO_FIELD_LIST][$key] = $user_info;
            }
        }

        $data_list = new DataListDto();
        $data_list->Assign($list);
        $responseDto->data = $data_list;
        return $responseDto;
    }

    // 检查用户是主管、售后客服还是线索客服？
    public function checkIsClueSale($user_info) {
        // if (2 != $user_info['department_id']) return false;         // 必须是售后部门
        if (User::LEVEL_STAFF != $user_info['level']) return false; // 必须是员工
        // 属于员工，并且没有订单权限，则为线索员工
        if (!$this->rolePrivilegeRepository) $this->rolePrivilegeRepository = new RolePrivilegeRepository();
        $role_privilege_ids = $this->rolePrivilegeRepository->getPrivilegesByRoleId($user_info['role_id']);
        //  26-就是售后订单管理, 通过角色ID获取角色对应角色进行判断是否有添加订单权限，暂无配置数据
        if (!in_array(26, $role_privilege_ids))
            return true;
        return false;
    }

}
