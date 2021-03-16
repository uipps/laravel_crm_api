<?php

namespace App\Repositories\Customer;

use App\Models\Customer\Customer;
use App\Repositories\Admin\DepartmentRepository;
use App\Repositories\Admin\UserRepository;

class CustomerRepository extends CustomerRepositoryImpl
{
    const CACHE_EXPIRE = 1;  // 单位秒，缓存时间

    private static function GetCacheKey($id) {
        return 'db:customer:detail-id-' . $id;
    }

    // 通过id获取信息
    public function getInfoById($id) {
        // 先从cache获取数据
        $cache_key = self::GetCacheKey($id);
        $cached_result = \Cache::get($cache_key);
        if ($cached_result)
            return $cached_result;

        // 再从数据库获取，获取到了则种cache
        $db_result = parent::getInfoById($id);
        if (!$db_result)
            return $db_result;
        \Cache::put($cache_key, $db_result, self::CACHE_EXPIRE);

        return $db_result;
    }


    public function getChildrenDepartmentByDeptId($dept_id) {
        // 此部门下的所有部门
        $all_departments = (new DepartmentRepository())->getAllDepartment();
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
        $user_list = (new UserRepository())->getUsersByDepartmentIds($staff_dept_list, 'id');
        if (!$user_list)
            return $user_list;
        $user_ids = array_column($user_list, 'id');
        return $user_ids;
    }


    // 已分配、未分配客户数； 线索统计数据，依据用户进行缓存
    //     level 岗位 1管理员,2员工
    public function getCustomerNumStatsByUserId($login_user_info) {
        // 统计各种数据
        $data_arr = [
            'customer_num_total' => 0,          // 客户数, 下面2项之和：
            'customer_distribute_no' => 0,      // 未分配客户
            'customer_distribute_yes' => 0,     // 已分配客户

            'clue_num_total' => 0,              // 线索数，下面2项之和
            'clue_distribute_no' => 0,          // 未分配线索
            'clue_distribute_yes' => 0,         // 已分配线索
            'clue_no_dealwith' => 0,            // 未处理线索
            'clue_dealwith' => 0,               // 已处理线索
        ];

        // 通过用户信息获取身份，管理员能看到所有
        $sql_where = [];
        if (1 != $login_user_info['role_id']) {
            if (2 == $login_user_info['level']) {
                // 员工，只有已分配
                $sql_where['after_sale_id'] = $login_user_info['id'];
            }  else {
                // 主管, 能看到
                /*$staff_dept_list = self::getChildrenDepartmentByDeptId($login_user_info['department_id']);
                $sql_where['department_id'] = ['in', $staff_dept_list]; // 线索需要使用，客户无此字段

                $user_list = (new UserRepository())->getUsersByDepartmentIds($staff_dept_list, 'id');
                if ($user_list) {
                    $user_ids = array_column($user_list, 'id');
                    $sql_where['after_sale_id'] = ['in', $user_ids];    // 客户表只有此字段
                }*/
            }
        }

        // 主管查看下属部门的所有员工总数据，员工则只能查看自己对应数据
        $data_arr['customer_distribute_no'] = parent::sqlCountCustomer(array_merge($sql_where, ['distribution_status'=>0]));
        $data_arr['customer_distribute_yes'] = parent::sqlCountCustomer(array_merge($sql_where, ['distribution_status'=>1]));

        $clue = new CustomerClueRepositoryImpl();
        // 线索
        $data_arr['clue_distribute_no'] = $clue->sqlCountCustomerClue(array_merge($sql_where, ['distribute_status'=>0]));
        $data_arr['clue_distribute_yes'] = $clue->sqlCountCustomerClue(array_merge($sql_where, ['distribute_status'=>1]));

        // 未处理线索是指已经分配出去，但是尚未处理的线索，不包含未分配的线索
        $request = [
            'post_sale_id' => ['>', 0],    // 不包含未分配的线索
            'opt_status' => 0,
            'finish_status' => 0,
        ];
        $data_arr['clue_no_dealwith'] = $clue->sqlCountCustomerClue(array_merge($sql_where, $request));
        $data_arr['clue_dealwith'] = $clue->sqlCountCustomerClue(array_merge($sql_where, ['opt_status'=>1]));

        // 总数计算
        $data_arr['customer_num_total'] = $data_arr['customer_distribute_no'] + $data_arr['customer_distribute_yes'];
        $data_arr['clue_num_total'] = $data_arr['clue_no_dealwith'] + $data_arr['clue_dealwith'];

        return $data_arr;
    }

}
