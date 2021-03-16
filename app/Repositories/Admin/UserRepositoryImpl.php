<?php

namespace App\Repositories\Admin;

use App\Models\Admin\Department;
use App\Models\Admin\User;
use App\Models\Admin\UserAttr;
use App\Repositories\BaseRepository;


class UserRepositoryImpl extends BaseRepository
{
    protected $model ;
    protected $userAttrModel ;
    protected $departmentModel;

    public function __construct() {
        $this->model = new User();
        $this->userAttrModel = new UserAttr();
        $this->departmentModel = new Department();
    }

    public function getModel() {
        return $this->model;
    }

    public function getUserAttrModel() {
        return $this->userAttrModel;
    }

    // 新增并返回主键ID
    public function insertGetId($data_arr) {
        $insertId = $this->model->create($data_arr);
        return $insertId->id;
    }

    // 通过email获取用户信息
    public function getByEmail($email) {
        $sql_where = [
            'email' => $email,
        ];
        $db_result = $this->model->where($sql_where)->first();
        if ($db_result) {
            $user_info = $db_result->toArray();
            $user_info = $this->getUserAttrByUserId($user_info['id'], $user_info); // 顺便获取用户角色、语言等属性
            return $user_info;
        }
        return $db_result;
    }

    // 获取用户信息
    public function getUserById($user_id, $with_passwd = false) {
        if ($with_passwd)
            $db_result = $this->model->find($user_id)->makeVisible(['password']);
        else
            $db_result = $this->model->find($user_id);
        if ($db_result) {
            $user_info = $db_result->toArray();
            $user_info = $this->getUserAttrByUserId($user_info['id'], $user_info);
            return $user_info;
        }
        return $db_result;
    }

    public function updateData($user_id, $data_arr) {
        $sql_where = [
            'id' => $user_id,
        ];
        $data_arr = $this->filterFields4InsertOrUpdate($data_arr);
        $db_result = $this->model->where($sql_where)->first();
        if ($db_result) return $db_result->update($data_arr);
        return 0;
    }

    // 返回总数和列表数据
    public function getList($params, $field = ['*']) {
        $page = isset($params['page']) ? $params['page'] : 1;
        $limit = (isset($params['limit']) && $params['limit'] > 0) ? $params['limit'] : parent::PAGE_SIZE;
        //if ($limit > parent::PAGE_SIZE_MAX) $limit = parent::PAGE_SIZE; // 是否限制最大数量

        // 售前还是售后job_type，需要连表user、sys_department两张表进行查询
        // 连表查询
        $the_table = $this->model->getTable() ;
        $department_table = $this->departmentModel->getTable();
        $field = [$the_table.'.*', $department_table.'.job_type'];  // 带上 job_type 部门类型

        $builder = $this->model::leftJoin($department_table, $department_table . '.id','=', $the_table . '.department_id');

        // 不展示删除的数据
        if (isset($params['status'])) {
            if (is_array($params['status']))
                $builder = $builder->whereIn($the_table . '.status', $params['status']);
            else
                $builder = $builder->where($the_table . '.status', $params['status']);
            unset($params['status']);
        }

        // 自动获取表字段，并自动拼装查询条件
        $params = array_filter($params, function($v){return $v !== '';}); // 空字符不参与搜索条件
        $tbl_fields_arr = parent::getTheTableFields();
        if ($tbl_fields_arr) {
            foreach ($params as $l_field => $val) {
                if (in_array($l_field, $tbl_fields_arr)) {
                    // 针对不同的数据类型，自动拼装
                    $builder = parent::joinTableBuild($builder, $val, $l_field, $the_table);
                } else if ('job_type' == $l_field) {
                    $builder = parent::joinTableBuild($builder, $val, $l_field, $department_table);
                }
            }
        }
        $builder = $builder->orderBy($the_table . '.id', 'DESC');

        return $this->pager($builder, $page, $limit, $field);
    }

    // 从另一张数据表获取数据
    public function getUserAttrByUserId($uid, $append_to_arr) {
        $db_result = $this->userAttrModel->where('user_id', $uid)->get()->toArray();
        if (!$db_result)
            return $append_to_arr;

        // 重新组织一下，不同属性组织成线性的。或者type_list进行循环
        $user_attrs = [];
        foreach ($db_result as $row) {
            switch ($row['type']) {
                case UserAttr::TYPE_ROLE:
                    $user_attrs['role_id'][] = $row['work_id'];       // 角色单选
                    break;
                default :
                    $user_attrs['unknown_type'][] = $row['work_id'];
                    break;
            }
        }

        if (!is_array($append_to_arr))
            $append_to_arr = [];
        // 将属性字段复制到指定数组上
        if (isset($user_attrs['role_id'])) {
            $append_to_arr['role_ids'] = implode(',', $user_attrs['role_id']);
            $append_to_arr['role_id'] = $user_attrs['role_id'][0]; // 当前只支持单选
        }
        if (isset($user_attrs['unknown_type'])) {
            $append_to_arr['unknown_type'] = implode(',', $user_attrs['unknown_type']);
        }
        return $append_to_arr;
    }

    // 获取批量
    public function getUserAttrByUserIdList($uid_list) {
        $db_result = $this->userAttrModel->whereIn('user_id', $uid_list)->get()->toArray(); // wherein
        //print_r($db_result->toArray());exit;
        if (!$db_result)
            return [];

        // 按照uid重新组织一下
        $users_attrs = [];
        foreach ($db_result as $row) {
            switch ($row['type']) {
                case UserAttr::TYPE_ROLE:
                    $users_attrs[$row['user_id']]['role_id'][] = $row['work_id'];       // 角色单选
                    break;
                default :
                    $users_attrs[$row['user_id']]['unknown_type'][] = $row['work_id'];
                    break;
            }
        }

        $append_to_arr = [];
        foreach ($users_attrs as $user_id => $user_attrs) {

            // 将属性字段复制到指定数组上
            if (isset($user_attrs['role_id'])) {
                $append_to_arr[$user_id]['role_ids'] = implode(',', $user_attrs['role_id']);
                $append_to_arr[$user_id]['role_id'] = $user_attrs['role_id'][0]; // 当前只支持单选
            }
            if (isset($user_attrs['unknown_type'])) {
                $append_to_arr[$user_id]['unknown_type'] = implode(',', $user_attrs['unknown_type']);
            }
        }

        return $append_to_arr;
    }

    // 拼装数组
    public function getUserAttrByRequest($uid, $data_arr, $with_create_time='', $with_creator_id=0) {
        $rlt = [];
        $row['user_id'] = $uid;
        $row['created_time'] = $with_create_time ?? date('Y-m-d H:i:s');
        $row['creator_id'] = $with_creator_id ?? 0;

        // role_id单选
        if (isset($data_arr['role_id'])) {
            $row['type'] = UserAttr::TYPE_ROLE;
            $row['work_id'] = $data_arr['role_id'];
            $rlt[] = $row;
        }
        return $rlt;
    }

    public function insertUserAttrMultiByUid($uid, $request, $create_time='', $creator_id=0) {
        $data_arr = self::getUserAttrByRequest($uid, $request, $create_time, $creator_id);
        $rlt = $this->userAttrModel->insert($data_arr);
        return $rlt;
    }

    public function updateUserAttrMultiByUid($uid, $data_arr, $create_time='', $creator_id=0) {
        // 有可能不存在，需要进行insert
        // 检查是否存在，不存在则insert，存在则更新
        $rlt = $this->userAttrModel->where('user_id', $uid)->get()->toArray(); // 支持多条
        $a_types = []; // 按照type类型归类
        if ($rlt) {
            foreach ($rlt as $row) {
                $a_types[$row['type']][] = $row['work_id']; // 实际上可能有多个
            }
        }

        $row = [];
        $row['user_id'] = $uid;

        if (isset($data_arr['role_id'])) {
            $row['type'] = UserAttr::TYPE_ROLE;
            $work_id = $data_arr['role_id'];
            // 检查是否存在，存在就更新
            if (!isset($a_types[$row['type']])) {
                $row['work_id'] = $work_id;
                $row['created_time'] = $create_time ?? date('Y-m-d H:i:s');
                $row['creator_id'] = $creator_id ?? 0;
                $this->userAttrModel->insert($row);
            } else if (!in_array($work_id, $a_types[$row['type']])) {
                $sql_where = []; // 避免用row的干扰
                $sql_where['user_id'] = $row['user_id'];
                $sql_where['type'] = $row['type'];
                //$this->userAttrModel->where($sql_where)->firstOrFail()->update(['work_id'=>$work_id]); // 角色是单选
                $db_result = $this->userAttrModel->where($sql_where)->first();
                if ($db_result) $db_result->update(['work_id'=>$work_id]);
            }
        }
        return true;
    }

    // 获取用户信息(无属性信息)
    public function getUsersByIds($user_ids) {
        $db_result = $this->model->whereIn('id', $user_ids)->get()->toArray();
        if ($db_result)
            return array_column($db_result, null, 'id');
        return $db_result;
    }
    public function getAllUser() {
        $db_result = $this->model->get()->toArray();
        if ($db_result)
            return array_column($db_result, null, 'id');
        return $db_result;
    }

    // 获取该部门下的有效员工数，(不包含子部门，单纯只是跟子部门同级别的员工)
    public function getDepartmentUserNum($dept_id) {
        $db_result = $this->model->where(['department_id'=>$dept_id])->where('status', '!=', User::STATUS_DELETE)->get('id')->toArray();
        if (!$db_result)
            return 0;
        return count($db_result);
    }

    public function getUsersByDepartmentIds($dept_ids, $field=['*']) {
        return $db_result = $this->model->whereIn('department_id', $dept_ids)->get($field)->toArray();
    }
}
