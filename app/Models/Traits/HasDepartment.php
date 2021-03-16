<?php

namespace App\Models\Traits;

use App\Mappers\CommonMapper;
use App\Models\Admin\Department;
use App\Models\Admin\User;
use App\Models\OrderPreSale\Order;
use App\Models\OrderPreSale\OrderManual;
use Illuminate\Database\Eloquent\Builder;

/**
 * @method static \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Query\Builder presalePemission
 * @method static \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Query\Builder userDepartment
 * @method static \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Query\Builder department
 * @method static \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Query\Builder departmentAudit
 * @method static \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Query\Builder departmentDistribute
 */
trait HasDepartment {
    protected static $allDepartment = null;

    // public static function bootHasDepartment() {

    //     static::addGlobalScope('department_id', function (Builder $query) {

    //     });

    // }

    /**
     * 当前用户过滤部门 如果角色是主管，则查询部门
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopePresalePemission(Builder $query)
    {
        $login_user_info = Auth('api')->user();

        if ($login_user_info['id'] > 1 && $login_user_info['level'] >0 ) {
            if (User::LEVEL_ADMIN == $login_user_info['level']) {
                // 主管能看到本部门的全部列表
                $dept_id = $login_user_info['department_id'];
                if(is_null(self::$allDepartment)){
                    self::$allDepartment = Department::get()->toArray();
                }

                $all_departments = self::$allDepartment;

                // 查询到部门下的所有子部门
                $staff_dept_list = \getAllChildIdByParentId($all_departments, $dept_id);
                if ($staff_dept_list) {
                    $staff_dept_list = array_column($staff_dept_list, 'id');
                }
                $staff_dept_list[] = $dept_id; // 加上本身所在部门

                return $query->whereIn('department_id', $staff_dept_list);

            } else if (User::LEVEL_STAFF == $login_user_info['level']) {
                // 员工只能看到自己的: 自己创建或分配给自己的
                return $query->where('pre_sale_id', $login_user_info['id']);   // 分配给当前用户的
            }
        }

        return $query;
    }


    /**
     * 当前用户过滤部门 如果角色是主管，则查询部门
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeUserDepartment(Builder $query)
    {
        $login_user_info = Auth('api')->user();

        if ($login_user_info['id'] > 1 && $login_user_info['level'] >0 ) {
            if (User::LEVEL_ADMIN == $login_user_info['level']) {
                // 主管能看到本部门的全部列表
                $dept_id = $login_user_info['department_id'];
                if(is_null(self::$allDepartment)){
                    self::$allDepartment = Department::get()->toArray();
                }

                $all_departments = self::$allDepartment;

                // 查询到部门下的所有子部门
                $staff_dept_list = \getAllChildIdByParentId($all_departments, $dept_id);
                if ($staff_dept_list) {
                    $staff_dept_list = array_column($staff_dept_list, 'id');
                }
                $staff_dept_list[] = $dept_id; // 加上本身所在部门

                return $query->whereIn('department_id', $staff_dept_list);

            } else if (User::LEVEL_STAFF == $login_user_info['level']) {
                // 员工只能看到自己的: 自己创建或分配给自己的
                return $query->where('post_sale_id', $login_user_info['id'])->OrWhere('creator_id', $login_user_info['id']);   // 分配给当前用户的
            }
        }

        return $query;
    }

    /**
     * 过滤部门
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeDepartmentPemission(Builder $query)
    {
        $login_user_info = Auth('api')->user();

        if ($login_user_info['id'] > 1 && $login_user_info['level'] >0 ) {
            if (User::LEVEL_ADMIN == $login_user_info['level']) {
                // 主管能看到本部门的全部列表
                $dept_id = $login_user_info['department_id'];
                if(is_null(self::$allDepartment)){
                    self::$allDepartment = Department::get()->toArray();
                }

                $all_departments = self::$allDepartment;

                // 查询到部门下的所有子部门
                $staff_dept_list = \getAllChildIdByParentId($all_departments, $dept_id);
                if ($staff_dept_list) {
                    $staff_dept_list = array_column($staff_dept_list, 'id');
                }
                $staff_dept_list[] = $dept_id; // 加上本身所在部门

                return $query->whereIn('department_id', $staff_dept_list);

            } else if (User::LEVEL_STAFF == $login_user_info['level']) {
                // 员工只能看到自己的
                return $query->where('department_id', $login_user_info['department_id']);
            }
        }

        return $query;
    }

    // 已审核、未审核列表用到的过滤条件
    public function scopeDepartmentAudit(Builder $query, $userId = 0)
    {
        if($userId){
            $login_user_info = User::find($userId);
        }else{
            $login_user_info = Auth('api')->user();
        }

        if ($login_user_info['id'] > 1 && $login_user_info['level'] >0 ) {
            if (User::LEVEL_ADMIN == $login_user_info['level']) {
                // 主管能看到本部门的全部列表
                $dept_id = $login_user_info['department_id'];
                if(is_null(self::$allDepartment)){
                    self::$allDepartment = Department::get()->toArray();
                }

                $all_departments = self::$allDepartment;
                // 查询到部门下的所有子部门
                $staff_dept_list = \getAllChildIdByParentId($all_departments, $dept_id);
                if ($staff_dept_list) {
                    $staff_dept_list = array_column($staff_dept_list, 'id');
                }
                $staff_dept_list[] = $dept_id; // 加上本身所在部门

                return $query->whereIn('order_audit.department_id', $staff_dept_list);

            } else if (User::LEVEL_STAFF == $login_user_info['level']) {
                // 员工只能看到自己的
                return $query->where('order_audit.audit_user_id', $login_user_info['id']);
            }
        }

        return $query;
    }

    // 已分配、未分配列表用到的过滤条件
    public function scopeDepartmentDistribute(Builder $query)
    {
        $login_user_info = Auth('api')->user();

        if ($login_user_info['id'] > 1 && $login_user_info['level'] >0 ) {
            if (User::LEVEL_ADMIN == $login_user_info['level']) {
                // 已分配，主管能看到需要部门处理的
                return $query->where('order_distribute.department_id', $login_user_info['department_id']);
            } else if (User::LEVEL_STAFF == $login_user_info['level']) {
                // 员工只能看到分配给自己的
                return $query->where('order_distribute.distributed_user_id', $login_user_info['id']);
            }
        }

        return $query;
    }

}
