<?php

namespace App\Repositories\Customer;

use App\Models\Admin\Department;
use App\Models\Admin\User;
use App\Models\Customer\CustomerRemark;
use App\Repositories\BaseRepository;

class CustomerRemarkRepositoryImpl extends BaseRepository
{
    protected $model ;
    protected $userModel;
    protected $departmentModel;

    public function __construct() {
        $this->model = new CustomerRemark();
        $this->userModel = new User();
        $this->departmentModel = new Department();
    }

    public function getList($params, $field = ['*']) {
        $page = isset($params['page']) ? $params['page'] : 1;
        $limit = (isset($params['limit']) && $params['limit'] > 0) ? $params['limit'] : parent::PAGE_SIZE;
        //if ($limit > parent::PAGE_SIZE_MAX) $limit = parent::PAGE_SIZE; // 是否限制最大数量

        // 创建者是售前还是售后job_type需要连表user、sys_department两张表进行查询
        // 连表查询
        $the_table = $this->model->getTable() ;
        $user_table = $this->userModel->getTable();
        $department_table = $this->departmentModel->getTable();
        $field = [$the_table.'.*', $department_table.'.job_type'];  // 带上 job_type 部门类型

        $builder = $this->model::leftJoin($user_table, $the_table . '.creator_id','=', $user_table . '.id')
            ->leftJoin($department_table, $department_table . '.id','=', $user_table . '.department_id');

        /*if (isset($params['status'])) {
            if (is_array($params['status']))
                $builder = $builder->whereIn('status', $params['status']);
            else
                $builder = $builder->where('status', $params['status']);
        }*/
        // 客户的备注列表，customer_id字段必须要提供
        //if (isset($params['customer_id'])) $builder = $builder->where('customer_id', $params['customer_id']);

        // 自动获取表字段，并自动拼装查询条件
        $params = array_filter($params, function($v){return $v !== '';}); // 空字符不参与搜索条件
        $tbl_fields_arr = parent::getTheTableFields();
        if ($tbl_fields_arr) {
            foreach ($params as $l_field => $val) {
                if (in_array($l_field, $tbl_fields_arr)) {
                    // 针对不同的数据类型，自动拼装
                    $builder = parent::joinTableBuild($builder, $val, $l_field, $the_table);
                }
            }
        }

        $builder = $builder->orderBy($the_table.'.id', 'DESC');
        return $this->pager($builder, $page, $limit, $field);
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
}
