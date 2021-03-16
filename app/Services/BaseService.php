<?php

namespace App\Services;


use App\Models\OrderPreSale\Order as PreOrderModel;
use App\Repositories\Admin\CountryRepository;
use App\Repositories\Admin\DepartmentRepository;
use App\Repositories\Admin\LanguageRepository;
use App\Repositories\Admin\RoleRepository;
use App\Repositories\Admin\UserRepository;
use App\Repositories\OrderPreSale\OrderOptTypeRepository;

abstract class  BaseService
{
    protected $_lastError;
    protected $userRepository;

    /**
     * @param  int   $code
     * @param string $message
     * @param array  $data
     *
     * @return array
     */
    protected function returnFormat($code, $message = '', $data = [])
    {
        return ['code' => intval($code), 'info' => $message, 'data' => $data];
    }

    /*
     * 设置错误信息
     * @return array
     */
    public function setLastError($code, $message = '')
    {
        $this->_lastError = ['code' => $code, 'info' => $message];
    }

    /*
     * 获取错误信息
     * @return array
     */
    public function getLastError()
    {
        return $this->_lastError ? $this->_lastError : ['code' => 0, 'info' => ''];
    }

    // 获取当前用户信息
    public function getCurrentLoginUserInfo() {
        $user_id = auth('api')->id(); // 存在于jwt token中
        if (!$user_id)
            return [];
        return $this->userRepository->getUserById($user_id);
    }

    public function getChildrenDepartmentByDeptId($dept_id) {
        // 此部门下的所有部门
        if (!$this->departmentRepository) $this->departmentRepository = new DepartmentRepository();
        $all_departments = $this->departmentRepository->getAllDepartment();
        // 查询到部门下的所有子部门
        $staff_dept_list = \getAllChildIdByParentId($all_departments, $dept_id);
        if ($staff_dept_list) {
            $staff_dept_list = array_column($staff_dept_list, 'id');
        }
        $staff_dept_list[] = $dept_id; // 加上本身所在部门
        return $staff_dept_list;
    }

    public function getChildrenUserIdsByDeptId($dept_id) {
        // 此部门下的所有员工，包括各级子部门的员工，先查所有部门
        $staff_dept_list = self::getChildrenDepartmentByDeptId($dept_id);
        $user_list = $this->userRepository->getUsersByDepartmentIds($staff_dept_list, 'id');
        if (!$user_list)
            return $user_list;
        $user_ids = array_column($user_list, 'id');
        return $user_ids;
    }

    // 包括系统设置和订单部分
    public function addAllAttrName2Data($data_arr, $attr_list=[], $only_field=[]) {
        $data_arr = self::addAttrName2Data($data_arr, $attr_list, $only_field);
        $data_arr = self::addOrderAttrName2Data($data_arr, $attr_list, $only_field);
        return $data_arr;
    }

    // $only_field=['status','country_id'] 可以指定只需要的字段，有时间再加
    public function addAttrName2Data($data_arr, $attr_list=[], $only_field=[]) {
        // 常见的 status_display，department_name，role_name，language_name(多条language_names)，level，country_name
        if (!is_array($data_arr)) // && !is_object($data_arr)
            return $data_arr;

        // 多语言支持
        $lang_field = 'en_name'; // 默认英文，需要依据请求中的语言设置，显示对应语言
        $department_field = 'name';
        $role_field = 'name';
        if (in_array(app()->getLocale(), ['zh_CN', 'zh-CN'])) {
            $lang_field = 'cn_name';
        }

        // 自动获取常用的全量数据
        if (!$only_field) { // 全部都检查
            self::addStatusName($data_arr, $attr_list);                         // 1. 状态 [0=>停用, 1=>启用]
            self::addCountryName($data_arr, $attr_list, $lang_field);           // 2. 国家
            self::addLanguageName($data_arr, $attr_list, $lang_field);          // 3. 语言，单条
            self::addLanguagesName($data_arr, $attr_list, $lang_field);         //   3.2 语言，多条，逗号分隔
            self::addRoleName($data_arr, $attr_list, $role_field);              // 4. 角色
            self::addDepartmentName($data_arr, $attr_list, $department_field);  // 5. 部门
            self::addLevelName($data_arr, $attr_list);                          // 6. 岗位类型
            self::addJobTypeName($data_arr, $attr_list);                        // 7. 部门类型
            self::addDistributeTypeName($data_arr, $attr_list);                 // 8. 分单类型
            return $data_arr;
        }

        // 指定了只替换部分的话
        if (!is_array($only_field))
            $only_field = [$only_field];
        foreach ($only_field as $field) {
            $field = strtolower($field);
            switch ($field) {
                case 'status':
                    self::addStatusName($data_arr, $attr_list);                         // 1. 状态 [0=>停用, 1=>启用]
                    break;
                case 'country_id':
                    self::addCountryName($data_arr, $attr_list, $lang_field);           // 2. 国家
                    break;
                case 'language_id':
                    self::addLanguageName($data_arr, $attr_list, $lang_field);          // 3. 语言，单条
                    break;
                case 'language_ids':
                    self::addLanguagesName($data_arr, $attr_list, $lang_field);         //   3.2 语言，多条，逗号分隔
                    break;
                case 'role_id':
                    self::addRoleName($data_arr, $attr_list, $role_field);              // 4. 角色
                    break;
                case 'department_id':
                    self::addDepartmentName($data_arr, $attr_list, $department_field);  // 5. 部门
                    break;
                case 'level':
                    self::addLevelName($data_arr, $attr_list);                          // 6. 岗位类型
                    break;
                case 'job_type':
                    self::addJobTypeName($data_arr, $attr_list);                        // 7. 部门类型
                    break;
                case 'distribute_type':
                    self::addDistributeTypeName($data_arr, $attr_list);                 // 8. 分单类型
                    break;
            }
        }
        return $data_arr;
    }

    // 1. 状态 [0=>停用, 1=>启用]
    public function addStatusName(&$data_arr, $attr_list=[]) {
        if (!isset($data_arr['status']))
            return $data_arr;
        $status_list = $attr_list['status_list'] ?? [0=>'停用', 1=>'启用'];
        $data_arr['status_display'] = $status_list[$data_arr['status']] ?? '';
        return $data_arr;
    }
    // 2. 国家
    public function addCountryName(&$data_arr, $attr_list=[], $lang_field='', $display_field='country_id', $display_name_field='country_name') {
        if (!isset($data_arr[$display_field]) || !$data_arr[$display_field])
            return $data_arr;
        if (!$lang_field) {
            // 多语言支持
            $lang_field = 'en_name'; // 默认英文，需要依据请求中的语言设置，显示对应语言
            if (in_array(app()->getLocale(), ['zh_CN', 'zh-CN'])) {
                $lang_field = 'cn_name';
            }
        }
        $country_list = $attr_list['country_list'] ?? (new CountryRepository())->getAllCountry();
        $data_arr[$display_name_field] = $country_list[$data_arr[$display_field]][$lang_field] ?? '-';
        return $data_arr;
    }
    // 3. 语言，单条
    public function addLanguageName(&$data_arr, $attr_list=[], $lang_field='', $field_id='language_id', $name_field='language_name') {
        if (!isset($data_arr[$field_id]) || !$data_arr[$field_id])
            return $data_arr;
        //if (0 == $data_arr[$field_id]) $data_arr[$name_field] = '所有语言';
        if (!$lang_field) {
            // 多语言支持
            $lang_field = 'en_name'; // 默认英文，需要依据请求中的语言设置，显示对应语言
            if (in_array(app()->getLocale(), ['zh_CN', 'zh-CN'])) {
                $lang_field = 'cn_name';
            }
        }
        $language_list = $attr_list['language_list'] ?? (new LanguageRepository())->getAllLanguage();
        $data_arr[$name_field] = $language_list[$data_arr[$field_id]][$lang_field] ?? '-';
        return $data_arr;
    }
    // 3.2 语言，多条的情况
    public function addLanguagesName(&$data_arr, $attr_list=[], $lang_field='') {
        if (!isset($data_arr['language_ids']) || !$data_arr['language_ids'])
            return $data_arr;
        if (!$lang_field) {
            // 多语言支持
            $lang_field = 'en_name'; // 默认英文，需要依据请求中的语言设置，显示对应语言
            if (in_array(app()->getLocale(), ['zh_CN', 'zh-CN'])) {
                $lang_field = 'cn_name';
            }
        }
        $language_list = $attr_list['language_list'] ?? (new LanguageRepository())->getAllLanguage();
        if (false !== strpos($data_arr['language_ids'], ',')) {
            $language_names = [];
            foreach (explode(',', $data_arr['language_ids']) as $language_id) {
                if ($language_id)
                    $language_names[] = $language_list[$language_id][$lang_field] ?? '-';
            }
            $data_arr['language_names'] = implode(',', $language_names);
        } else {
            // 单个
            $data_arr['language_names'] = $language_list[$data_arr['language_ids']][$lang_field] ?? '-';
        }
        return $data_arr;
    }
    // 4. 角色
    public function addRoleName(&$data_arr, $attr_list=[], $role_field='') {
        if (!isset($data_arr['role_id']) || !$data_arr['role_id'])
            return $data_arr;
        if (!$role_field) {
            $role_field = 'name'; // 角色暂未设置多语言
        }
        $role_list = $attr_list['role_list'] ?? (new RoleRepository())->getAllRole();
        $data_arr['role_name'] = $role_list[$data_arr['role_id']][$role_field] ?? '-';
        return $data_arr;
    }
    // 5. 部门
    public function addDepartmentName(&$data_arr, $attr_list=[], $department_field='') {
        if (!isset($data_arr['department_id']))
            return $data_arr;
        if (0 == $data_arr['department_id']) $data_arr['department_name'] = '所有部门';
        if (!$department_field) {
            $department_field = 'name'; // 部门暂未设置多语言
        }
        $department_list = $attr_list['department_list'] ??  (new DepartmentRepository())->getAllDepartment();
        $data_arr['department_name'] = $department_list[$data_arr['department_id']][$department_field] ?? '-';
        return $data_arr;
    }
    // 6. 岗位类型
    public function addLevelName(&$data_arr, $attr_list=[]) {
        if (!isset($data_arr['level']))
            return $data_arr;
        $level_list = $attr_list['level_list'] ?? [1=>'主管', 2=>'员工'];
        $data_arr['level_name'] = $level_list[$data_arr['level']] ?? '-';
        return $data_arr;
    }
    // 7. 部门类型
    public function addJobTypeName(&$data_arr, $attr_list=[]) {
        if (!isset($data_arr['job_type']))
            return $data_arr;
        $job_type_list = $attr_list['job_type_list'] ?? [1=>'售前', 2=>'售后'];
        $data_arr['job_type_display'] = $job_type_list[$data_arr['job_type']] ?? '-';
        return $data_arr;
    }
    // 8. 分单类型
    public function addDistributeTypeName(&$data_arr, $attr_list=[]) {
        if (!isset($data_arr['distribute_type']))
            return $data_arr;
        $distribute_type_list = $attr_list['distribute_type_list'] ?? [0=>'手动', 1=>'自动'];
        $data_arr['distribute_type_display'] = $distribute_type_list[$data_arr['distribute_type']] ?? '-';
        return $data_arr;
    }

    //////////  订单相关 //////////
    public function addOrderAttrName2Data($data_arr, $attr_list=[], $only_field=[]) {
        if (!is_array($data_arr))
            return $data_arr;
        // 暂无多语言
        self::addOrderSourceName($data_arr, $attr_list);        // 1. 订单来源
        self::addOrderTypeName($data_arr, $attr_list);          // 2.
        self::addPreSaleIdName($data_arr, $attr_list);          // 3.
        self::addHistoryPreSaleIdName($data_arr, $attr_list);   // 4.
        self::addAfterSaleIdName($data_arr, $attr_list);        // 5.
        self::addStatusName($data_arr, $attr_list);             // 系统管理里面也有状态，多一次没关系
        return $data_arr;
    }
    // 1. 订单来源，无多语言，$lang_field参数不用
    public function addOrderSourceName(&$data_arr, $attr_list=[], $lang_field='', $field_id='order_source') {
        if (!isset($data_arr[$field_id]))
            return $data_arr;
        $l_list = $attr_list[$field_id . '_list'] ?? PreOrderModel::ORDER_SOURCE_LIST;
        $data_arr[$field_id . '_name'] = $l_list[$data_arr[$field_id]] ?? '-';
        return $data_arr;
    }
    // 2. 订单类型
    public function addOrderTypeName(&$data_arr, $attr_list=[], $lang_field='', $field_id='order_type') {
        if (!isset($data_arr[$field_id]))
            return $data_arr;
        $l_list = $attr_list[$field_id . '_list'] ?? PreOrderModel::ORDER_TYPE_LIST;
        $data_arr[$field_id . '_name'] = $l_list[$data_arr[$field_id]] ?? '-';
        return $data_arr;
    }
    // 3. 售前客服，pre_sale_id，对应用户表id
    public function addPreSaleIdName(&$data_arr, $attr_list=[], $lang_field='real_name', $field_id='pre_sale_id', $name_field='pre_sale_name') {
        if (!isset($data_arr[$field_id]))
            return $data_arr;
        $l_list = $attr_list[$field_id . '_list'] ?? (new UserRepository())->getAllUser();
        $data_arr[$name_field] = $l_list[$data_arr[$field_id]][$lang_field] ?? '-';
        return $data_arr;
    }
    // 4. 曾售前客服，history_pre_sale_id，同样对应用户表id
    public function addHistoryPreSaleIdName(&$data_arr, $attr_list=[], $lang_field='real_name', $field_id='history_pre_sale_id', $name_field='history_pre_sale_name') {
        if (!isset($data_arr[$field_id]))
            return $data_arr;
        $l_list = $attr_list[$field_id . '_list'] ?? (new UserRepository())->getAllUser();
        $data_arr[$name_field] = $l_list[$data_arr[$field_id]][$lang_field] ?? '-';
        return $data_arr;
    }
    // 5. 售后客服，after_sale_id，对应用户表id
    public function addAfterSaleIdName(&$data_arr, $attr_list=[], $lang_field='real_name', $field_id='after_sale_id', $name_field='after_sale_name') {
        if (!isset($data_arr[$field_id]))
            return $data_arr;
        $l_list = $attr_list[$field_id . '_list'] ?? (new UserRepository())->getAllUser();
        $data_arr[$name_field] = $l_list[$data_arr[$field_id]][$lang_field] ?? '-';
        return $data_arr;
    }
    // 6. 手工订单状态名称，status_manual，暂时不用了，前端自己翻译
    /*public function addManualStatusIdName(&$data_arr, $attr_list=[], $lang_field='', $field_id='status_manual', $name_field='status_manual_name') {
        if (!isset($data_arr[$field_id]))
            return $data_arr;
        $status_list = $attr_list[$field_id . '_list'] ?? [0=>'未提交', 1=>'已提交',-1=>'已取消'];
        $data_arr[$name_field] = $status_list[$data_arr[$field_id]] ?? '';
        return $data_arr;
    }*/
    // 6. 订单操作类型
    public function addOptTypeName(&$data_arr, $attr_list=[], $lang_field='', $field_id='opt_type_id', $name_field='opt_type_name') {
        if (!isset($data_arr[$field_id]))
            return $data_arr;
        if (!$lang_field) {
            // 多语言支持
            $lang_field = 'name'; // 默认中文，英文没人维护，需要依据请求中的语言设置，显示对应语言
            if (!in_array(app()->getLocale(), ['zh_CN', 'zh-CN'])) {
                $lang_field = 'en_name';
            }
        }
        $l_list = $attr_list[$field_id . '_list'] ?? (new OrderOptTypeRepository())->getAllOptType();
        $data_arr[$name_field] = $l_list[$data_arr[$field_id]][$lang_field] ?? '-';
        return $data_arr;
    }

    // 7. 拼装活动规则名称
    public function addPromotionRuleName(&$data_arr, $name_field='rule_name') {
        $data_arr[$name_field] = '满' .$data_arr['min_num'] . '件总价' . rtrim(bcmul($data_arr['discount'],10,1), '. 0') . '折';
        return $data_arr;
    }

    // 8. 线索客服，creator_id，对应用户表id
    public function addCreatorName(&$data_arr, $attr_list=[], $lang_field='real_name', $field_id='creator_id', $name_field='creator_name') {
        if (!isset($data_arr[$field_id]))
            return $data_arr;
        $l_list = $attr_list[$field_id . '_list'] ?? (new UserRepository())->getAllUser();
        $data_arr[$name_field] = $l_list[$data_arr[$field_id]][$lang_field] ?? '-';
        return $data_arr;
    }
    // 9. 分配的客服，after_sale_id，对应用户表id
    public function addPostSaleIdName(&$data_arr, $attr_list=[], $lang_field='real_name', $field_id='post_sale_id', $name_field='post_sale_name') {
        if (!isset($data_arr[$field_id]))
            return $data_arr;
        $l_list = $attr_list[$field_id . '_list'] ?? (new UserRepository())->getAllUser();
        $data_arr[$name_field] = $l_list[$data_arr[$field_id]][$lang_field] ?? '-';
        return $data_arr;
    }

}
