<?php

namespace App\Repositories\Admin;

use App\Models\Admin\Role;
use App\Models\Admin\RolePrivilege;
use App\Models\Admin\SysRouting;
use App\Models\Admin\User as UserModel;
use App\Models\Admin\UserAttr as UserAttrModel;
use App\Repositories\BaseRepository;

class RoleRepositoryImpl extends BaseRepository
{
    protected $model ;
    protected $rolePrivilegeModel;
    protected $sysRoutingModel;
    protected $userAttrModel;

    public function __construct() {
        $this->model = new Role();
        $this->rolePrivilegeModel = new RolePrivilege();
    }

    public function getModel() {
        return $this->model;
    }

    public function getRolePrivilegeModel() {
        return $this->rolePrivilegeModel;
    }

    public function getList($params, $field = ['*']) {
        $page = isset($params['page']) ? $params['page'] : 1;
        $limit = (isset($params['limit']) && $params['limit'] > 0) ? $params['limit'] : parent::PAGE_SIZE;
        //if ($limit > parent::PAGE_SIZE_MAX) $limit = parent::PAGE_SIZE; // 是否限制最大数量

        $builder = $this->model;
        if (isset($params['status'])) {
            if (is_array($params['status']))
                $builder = $builder->whereIn('status', $params['status']);
            else
                $builder = $builder->where('status', $params['status']);
            unset($params['status']);
        } else {
            $builder = $builder->where('status', '!=', -1);
        }
        if (!isset($params['id'])) $params['id'] = ['>', 1];    // 不显示超管角色

        // 自动获取表字段，并自动拼装查询条件
        $params = array_filter($params, function($v){return $v !== '';}); // 空字符不参与搜索条件
        $tbl_fields_arr = parent::getTheTableFields();
        if ($tbl_fields_arr) {
            foreach ($params as $l_field => $val) {
                if (in_array($l_field, $tbl_fields_arr)) {
                    // 针对不同的数据类型，自动拼装
                    $builder = parent::joinTableBuild($builder, $val, $l_field);
                }
            }
        }

        $builder = $builder->orderBy('id', 'DESC');
        return $this->pager($builder, $page, $limit, $field);
    }

    // 通过id获取信息
    public function getInfoById($id) {
        $db_result = $this->model->find($id);
        if (!$db_result)
            return $db_result;
        return $db_result->toArray();
    }

    // 获取roleid对应的全部权限id一起返回
    public function getRoleUrlPrivilegeByRoleId($role_id) {
        // 从另一张关联表获取数据
        $privileges_db = $this->rolePrivilegeModel->where('role_id', $role_id)->get()->toArray(); // 角色所有权限
        if (!$privileges_db) {
            return [];
        }
        $privilege_list = array_column($privileges_db, null, 'privilege_id');

        // TODO 是否校验 privilege_list 的父子关系是否有重叠、去重等

        // 再通过权限ID获取对应url
        if (!$this->sysRoutingModel)
            $this->sysRoutingModel = new SysRouting();
        $routing_url_list = $this->sysRoutingModel->whereIn('privilege_id', array_keys($privilege_list))->get()->toArray(); // 获取全部url信息
        if (!$routing_url_list) {
            return ['privilege_list'=>$privilege_list];
        }

        // privilege_id 可能对应多个url
        $routing_list = [];
        foreach ($routing_url_list as $row) {
            //$routing_list[$row['privilege_id']][$row['url']] = $row;
            $routing_list[$row['url']] = $row;
        }

        return ['privilege_list'=>$privilege_list, 'routing_list'=>$routing_list]; // 权限和url都返回
    }

    // 通过role_id列表批量获取权限和url，按照role-id归类
    public function getRolesUrlsPrivilegesByRoleIdList($role_id_list) {
        if (!$role_id_list) return [];
        $rlt = [];

        // 从另一张关联表获取数据
        $privileges_db = $this->rolePrivilegeModel->whereIn('role_id', $role_id_list)->get()->toArray(); // 角色所有权限
        if (!$privileges_db) {
            return [];
        }

        $privilege_list = array_column($privileges_db, null, 'privilege_id');
        $role_priv_list = [];
        foreach ($privileges_db as $role_priv) {
            $role_priv_list[$role_priv['role_id']][$role_priv['privilege_id']] = $role_priv;
        }

        // 再通过权限ID获取对应url
        if (!$this->sysRoutingModel)
            $this->sysRoutingModel = new SysRouting();
        $routing_url_list = $this->sysRoutingModel->whereIn('privilege_id', array_keys($privilege_list))->get()->toArray(); // 获取全部url信息
        if (!$routing_url_list) {
            // 按照role_id组织一下数组 ，只需要返回权限列表
            foreach ($role_priv_list as $role_id => $value_privilege) {
                $rlt[$role_id]['privilege_list'] = $value_privilege;
            }
            return $rlt;
        }

        // privilege_id 可能对应多个url
        $routing_list = [];
        foreach ($routing_url_list as $row) {
            $routing_list[$row['privilege_id']][$row['url']] = $row;
        }

        // 按照role_id组织一下数组，返回权限和url列表，权限和路由，分成两个字段存放
        foreach ($role_priv_list as $role_id => $value_privilege) {
            $rlt[$role_id]['privilege_list'] = $value_privilege;

            // 通过privilege_id列表获取对应的routing列表信息
            $role_routing_list = [];
            foreach ($value_privilege as $l_privilege_id => $item) {
                if (isset($routing_list[$l_privilege_id])) {
                    //$role_routing_list[$l_privilege_id] = $routing_list[$l_privilege_id];
                    $role_routing_list = $routing_list[$l_privilege_id]; // 减少一层
                }
            }
            $rlt[$role_id]['routing_list'] = $role_routing_list;
        }
        return $rlt;
    }

    // 新增并返回主键ID
    public function insertGetId($data_arr) {
        $insertId = $this->model->create($data_arr);
        return $insertId->id;
    }

    public function updateData($id, $data_arr) {
        $sql_where = [
            'id' => $id,
        ];
        $data_arr = $this->filterFields4InsertOrUpdate($data_arr);
        if (isset($data_arr['id'])) unset($data_arr['id']);

        $db_result = $this->model->where($sql_where)->first();
        if ($db_result) return $db_result->update($data_arr);
        return 0;
    }

    // 拼装数组
    public function getPrivilegesByRequest($role_id, $data_arr) {
        $rlt = [];
        $row['role_id'] = $role_id;

        // privilege_ids 可能是多条, 用逗号分隔
        if (isset($data_arr['privilege_ids'])) {
            //$privilege_list = explode(',', $data_arr['privilege_ids']); // 逗号分隔
            $privilege_list = $data_arr['privilege_ids'];
            foreach ($privilege_list as $priv_id) {
                $row['privilege_id'] = $priv_id;
                $rlt[] = $row;
            }
        }
        return $rlt;
    }

    public function insertMultiPrivilegeByRoleid($role_id, $request, $create_time='', $creator_id=0) {
        $data_arr = self::getPrivilegesByRequest($role_id, $request);
        if ($data_arr) {
            $rlt = $this->rolePrivilegeModel->insert($data_arr);
            return $rlt;
        }
        return false;
    }
    public function updateMultiPrivilegeByRoleid($role_id, $data_arr, $create_time='', $creator_id=0) {
        // 有可能不存在，需要进行insert
        // 检查是否存在，不存在则insert，存在则更新
        if (!isset($data_arr['privilege_ids']))
            return false;

        $privileges_db = $this->rolePrivilegeModel->where('role_id', $role_id)->get()->toArray(); // 角色所有权限
        if (!$privileges_db) {
            // 无数据则直接插入
            $this->insertMultiPrivilegeByRoleid($role_id, $data_arr);
            return true;
        }

        $privilege_ids_db = array_column($privileges_db, 'privilege_id');
        //$privilege_list = explode(',', $data_arr['privilege_ids']); // 逗号分隔
        $privilege_list = $data_arr['privilege_ids'];

        // 批量换一下，删除旧的不存在于新数组中的；新增存在于新的不在旧数组中的
        $delete_ids = array_diff($privilege_ids_db, $privilege_list); // 需要删除的
        $add_ids = array_diff($privilege_list, $privilege_ids_db); // 需要新增的

        $row = [];
        $row['role_id'] = $role_id;
        if ($delete_ids) {
            // 需要删除的
            foreach ($delete_ids as $work_id) {
                $row['privilege_id'] = $work_id;
                $this->rolePrivilegeModel->where($row)->delete(); // TODO 寻找批量删除的方法
            }
        }
        if ($add_ids) {
            // 需要新增的
            $rows = [];
            foreach ($add_ids as $work_id) {
                $row['privilege_id'] = $work_id;
                $rows[] = $row;
            }
            $this->rolePrivilegeModel->insert($rows); // 批量插入
        }
        return true;
    }

    public function delete($role_id) {
        $sql_where['id'] = $role_id;
        return $this->model->where($sql_where)->delete();
    }

    // 全量（包括各种状态）
    public function getAllRole() {
        $db_result = $this->model->get()->toArray();
        if ($db_result)
            return array_column($db_result, null, 'id');
        return $db_result;
    }

    // 获取role-id关联的有效用户数
    public function getRoleValidUserNum($role_id) {
        // 需要从userAttr用户属性表查询
        $this->userAttrModel = new UserAttrModel();

        // 连表查
        $builder = $this->userAttrModel::leftJoin('user', 'user_attr.user_id','=','user.id');

        $sql_where = [
            'user_attr.work_id' => $role_id,
            'user_attr.type' => UserAttrModel::TYPE_ROLE,
            'user.status' => UserModel::STATUS_NORMAL,
        ];
        $builder = $builder->where($sql_where);
        $db_result = $builder->get(['user.id'])->toArray();
        if (!$db_result)
            return 0;
        $user_ids = array_column($db_result, 'id', 'id'); // 是唯一的user_id
        return count($user_ids);
    }
}
