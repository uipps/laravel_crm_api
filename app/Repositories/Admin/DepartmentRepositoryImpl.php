<?php

namespace App\Repositories\Admin;

use App\Models\Admin\Department;
use App\Repositories\BaseRepository;

class DepartmentRepositoryImpl extends BaseRepository
{
    protected $model ;

    public function __construct() {
        $this->model = new Department();
    }

    public function getModel() {
        return $this->model;
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

        $builder = $builder->orderBy('id', 'ASC');
        return $this->pager($builder, $page, $limit, $field);
    }

    // 全量（包括各种状态）
    public function getAllDepartment() {
        $db_result = $this->model->get()->toArray();
        if ($db_result)
            return array_column($db_result, null, 'id');
        return $db_result;
    }

    // 通过id获取信息
    public function getInfoById($id) {
        $db_result = $this->model->find($id);
        if (!$db_result)
            return $db_result;
        return $db_result->toArray();
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


    public function updateByIdAndCondition($id, $data_arr, $condition=[]) {
        $sql_where = [
            'id' => $id,
        ];
        if ($condition)
            $sql_where = array_merge($sql_where, $condition);

        $data_arr = $this->filterFields4InsertOrUpdate($data_arr);
        if (isset($data_arr['id'])) unset($data_arr['id']);

        $db_result = $this->model->where($sql_where)->first();
        if ($db_result) return $db_result->update($data_arr);
        return 0;
    }

}
