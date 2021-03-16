<?php

namespace App\Repositories\Admin;

use App\Models\Admin\DepartmentWeight;
use App\Repositories\BaseRepository;
use Illuminate\Support\Facades\Log;

class DepartmentWeightRepositoryImpl extends BaseRepository
{
    protected $model ;

    public function __construct() {
        $this->model = new DepartmentWeight();
    }

    public function getModel() {
        return $this->model;
    }

    public function getList($params, $field = ['*']) {
        $page = isset($params['page']) ? $params['page'] : 1;
        $limit = (isset($params['limit']) && $params['limit'] > 0) ? $params['limit'] : parent::PAGE_SIZE;
        //if ($limit > parent::PAGE_SIZE_MAX) $limit = parent::PAGE_SIZE; // 是否限制最大数量

        $builder = $this->model;

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

    public function getAllDepartmentWeight($status=null) {
        $builder = $this->model;

        if (is_numeric($status)) {
            $sql_where = [
                'status' => $status,
            ];
            $builder = $builder->where($sql_where);
        } else if (is_array($status)) {
            $builder = $builder->whereIn('status', $status);
        }
        $db_result = $builder->get()->toArray();

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

    public function updateDataByDeptIdCountryId($dept_id, $country_id, $data_arr) {
        $sql_where = [
            'department_id' => $dept_id,
            'country_id' => $country_id,
        ];
        $data_arr = $this->filterFields4InsertOrUpdate($data_arr);
        if (isset($data_arr['id'])) unset($data_arr['id']);

        //return $this->model->where($sql_where)->firstOrFail()->update($data_arr); // 是唯一的
        $db_result = $this->model->where($sql_where)->first();
        if ($db_result) return $db_result->update($data_arr);
        return 0;
    }

    // 设置部门分单比例, 多个部门放在一起，一起提交分单比例，必须保证合计100，需要用到事务
    public function setDeptOrderRate($param) {
        // 手动处理事务，\DB::beginTransaction(); 成功 \DB::commit();  失败 \DB::rollBack();
        \DB::beginTransaction();
        try {
            foreach ($param as $row) {
                $this->model->where('id', $row['id'])->firstOrFail()->update($row);
                //throw new \Exception($this->model->getTable() . ' transaction error!');
            }
            \DB::commit(); // 放到最后也可
        } catch(\Exception $e){
            \Log::error('db-Transaction-error: table, ' . $this->model->getTable() . ' datat: ' . print_r($row, true));
            \DB::rollBack();
            return false;
        }
        return true;

        // 也可自动处理 TODO
        /*$rlt = \DB::transaction(function () use ($param) {
            foreach ($param as $row) {
                $this->model->where('id', $row['id'])->firstOrFail()->update($row);
                Log::info(time() . 'db-Transaction-error: table, ' . $this->model->getTable() . ' datat: ' . print_r($row, true));
            }
        }, 3);
        //var_dump($rlt);
        return true;*/
    }

    // 通过获取某个部门（可无）某指定国家（可多个）的权重、分单比例
    public function getDeptOrderRateByCountry($params) {
        if (!isset($params['country_ids']) || !$params['country_ids'] || !isset($params['parent_id'])) { // 国家必须提供
            return [];
        }
        $country_ids = $params['country_ids'];

        $sql_where = [
            'sys_department.parent_id' => $params['parent_id'],
            'sys_department.status' => 1,                       // 只有开启状态的
        ];
        if (isset($params['department_id']) && $params['department_id']) {
            $sql_where['sys_department_weight.department_id'] = $params['department_id'];
        }

        $builder = $this->model::leftJoin('sys_department', 'sys_department.id','=','sys_department_weight.department_id');

        if (is_array($country_ids)) {
            $builder = $builder->where($sql_where)->whereIn('sys_department_weight.country_id', $country_ids);
        } else if (is_numeric($country_ids)) {
            $sql_where['sys_department_weight.country_id'] = $country_ids; // 单个国家
            $builder = $builder->where($sql_where);
        } else {
            $builder = $builder->where($sql_where)->whereIn('sys_department_weight.country_id', explode(',', $country_ids)); // wherein
        }
        $field = ['sys_department_weight.*', 'sys_department.name as department_name'];

        return $this->listNoPager($builder, $field);
    }

    // 拼装数组
    public function getWeightByRequest($dept_id, $data_arr, $create_type=1, $with_create_time='', $with_creator_id=0) {
        $rlt = [];
        if (!isset($data_arr['data']) || !$data_arr['data']) {
            return $rlt;
        }
        // 类型转换一下
        if (is_string($data_arr['data'])) {
            $data_arr['data'] = json_decode($data_arr['data'], true);
        }
        if (!is_array($data_arr['data'])) return [];

        foreach ($data_arr['data'] as $val) {
            $row = [];
            $row['department_id'] = $dept_id;
            $row['country_id'] = isset($val['country_id']) ? $val['country_id'] : 0;
            $row['weight'] = isset($val['weight']) ? $val['weight'] : 0;
            $row['ratio'] = isset($val['ratio']) ? $val['ratio'] : 0;
            $row['remark'] = isset($val['remark']) ? $val['remark'] : '';
            $row['status'] = DepartmentWeight::STATUS_1; // 默认启用, 不受status参数影响
            if ($create_type) {
                // 新增还是更新
                $row['created_time'] = $with_create_time ?? date('Y-m-d H:i:s');
                $row['creator_id'] = $with_creator_id ?? 0;
                $row['updated_time'] = $row['created_time'];
                $row['updator_id']   = $row['creator_id'];
            } else {
                $row['updated_time'] = $with_create_time ?? date('Y-m-d H:i:s');
                $row['updator_id']   = $with_creator_id ?? 0;
            }

            $rlt[] = $row;
        }

        return $rlt;
    }

    public function insertDepartWeightMultiByDeptId($dept_id, $request, $create_time='', $creator_id=0) {
        $data_arr = self::getWeightByRequest($dept_id, $request, 1, $create_time, $creator_id);
        if ($data_arr) {
            $rlt = $this->model->insert($data_arr); // 插入多条
            return $rlt;
        }
        return false;
    }

    public function updateDepartmentWeightMultiByDeptId($dept_id, $data_arr, $create_time='', $creator_id=0) {
        // 有可能不存在，需要进行insert
        // 检查是否存在，不存在则insert，存在则更新
        if (!isset($data_arr['data']))
            return false;
        // 类型转换一下
        if (is_string($data_arr['data'])) {
            $data_arr['data'] = json_decode($data_arr['data'], true);
        }
        if (!is_array($data_arr['data'])) return [];

        $exits_db = $this->model->where('department_id', $dept_id)->get()->toArray();
        if (!$exits_db) {
            // 无数据则直接插入
            $this->insertDepartWeightMultiByDeptId($dept_id, $data_arr, $create_time, $creator_id);
            return true;
        }

        $country_ids_db = array_column($exits_db, 'country_id');
        //$data_list = explode(',', $data_arr['data']); // 逗号分隔
        $data_list = $data_arr['data'];
        $country_ids_new = array_column($data_list, 'country_id');

        // 批量换一下，删除旧的不存在于新数组中的；新增存在于新的不在旧数组中的
        $delete_ids = array_diff($country_ids_db, $country_ids_new); // 需要删除的
        $add_ids = array_diff($country_ids_new, $country_ids_db); // 需要新增的
        $comm_ids = array_intersect($country_ids_new, $country_ids_db); // 交集需要更新

        $row = [];
        $row['department_id'] = $dept_id;
        if ($delete_ids) {
            // 需要删除的
            foreach ($delete_ids as $work_id) {
                $row['country_id'] = $work_id;
                $this->model->where($row)->delete(); // TODO 寻找批量删除的方法
            }
        }
        $data_list_row = array_column($data_list, null, 'country_id');
        if ($add_ids) {
            // 需要新增的
            foreach ($add_ids as $work_id) {
                $row = $data_list_row[$work_id]; // TODO 字段是否需要校验一下
                $row['department_id'] = $dept_id;
                $this->insertGetId($row);
            }
        }
        if ($comm_ids) {
            // 需要更新的
            foreach ($comm_ids as $work_id) {
                $row = $data_list_row[$work_id]; //
                if (isset($row['id'])) unset($row['id']); // 参数上携带的id不可信
                if (isset($row['department_id'])) unset($row['department_id']); // 测试的时候有脏数据，强制删除
                if (isset($row['country_id'])) unset($row['country_id']);

                //if (isset($row['id']))
                    //$this->updateData($row['id'], $row); // 更新
                //else {
                    $this->updateDataByDeptIdCountryId($dept_id, $work_id, $row);
                //}
            }
        }
        return true;
    }

    // 获取部门的国家、分单比例列表
    public function getDeptWeightByDeptId($dept_id) {
        $exits_db = $this->model->where('department_id', $dept_id)->get()->toArray();
        if (!$exits_db)
            return $exits_db;
        return array_column($exits_db, null, 'country_id'); // 按照国家设置的分单比例
    }
}
