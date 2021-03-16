<?php

namespace App\Repositories\Admin;

use App\Models\Admin\UserWeight;
use App\Repositories\BaseRepository;

class UserWeightRepositoryImpl extends BaseRepository
{
    protected $model ;

    public function __construct() {
        $this->model = new UserWeight();
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

    public function updateDataByUserIdLanguageId($user_id, $language_id, $data_arr) {
        $sql_where = [
            'user_id' => $user_id,
            'language_id' => $language_id,
        ];
        $data_arr = $this->filterFields4InsertOrUpdate($data_arr);
        if (isset($data_arr['id'])) unset($data_arr['id']);

        // 是唯一
        $db_result = $this->model->where($sql_where)->first();
        if ($db_result) return $db_result->update($data_arr);
        return 0;
    }

    public function getAllUserWeight($status=null) {
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

    // 拼装数组
    public function getWeightByRequest($user_id, $data_arr, $create_type=1, $with_create_time='', $with_creator_id=0) {
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
            $row['user_id'] = $user_id;
            $row['language_id'] = isset($val['language_id']) ? $val['language_id'] : 0;
            $row['weight'] = isset($val['weight']) ? $val['weight'] : 0;
            $row['ratio'] = isset($val['ratio']) ? $val['ratio'] : 0;
            $row['remark'] = isset($val['remark']) ? $val['remark'] : '';
            $row['status'] = isset($val['status']) ? $val['status'] : UserWeight::STATUS_1; // 默认启用
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

    public function insertUserWeightMultiByUserId($user_id, $request, $create_time='', $creator_id=0) {
        $data_arr = self::getWeightByRequest($user_id, $request, 1, $create_time, $creator_id);
        if ($data_arr) {
            $rlt = $this->model->insert($data_arr); // 插入多条
            return $rlt;
        }
        return false;
    }

    public function updateUserWeightMultiByUserId($user_id, $data_arr, $create_time='', $creator_id=0) {
        // 有可能不存在，需要进行insert
        // 检查是否存在，不存在则insert，存在则更新
        if (!isset($data_arr['data']))
            return false;
        // 类型转换一下
        if (is_string($data_arr['data'])) {
            $data_arr['data'] = json_decode($data_arr['data'], true);
        }
        if (!is_array($data_arr['data'])) return [];

        $exits_db = $this->model->where('user_id', $user_id)->get()->toArray(); // 角色所有权限, toArray()返回数组，可能是空数组
        if (!$exits_db) {
            // 无数据则直接插入
            $this->insertUserWeightMultiByUserId($user_id, $data_arr, $create_time, $creator_id);
            return true;
        }

        $language_ids_db = array_column($exits_db, 'language_id');
        $language_ids_new = array_column($data_arr['data'], 'language_id');

        // 批量换一下，删除旧的不存在于新数组中的；新增存在于新的不在旧数组中的
        $delete_ids = array_diff($language_ids_db, $language_ids_new); // 需要删除的
        $add_ids = array_diff($language_ids_new, $language_ids_db); // 需要新增的
        $comm_ids = array_intersect($language_ids_new, $language_ids_db); // 交集需要更新

        $row = [];
        $row['user_id'] = $user_id;
        if ($delete_ids) {
            // 需要删除的
            foreach ($delete_ids as $work_id) {
                $row['language_id'] = $work_id;
                $this->model->where($row)->delete();
            }
        }
        $data_list_row = array_column($data_arr['data'], null, 'language_id');
        if ($add_ids) {
            // 需要新增的
            $rows = [];
            foreach ($add_ids as $work_id) {
                $row = $data_list_row[$work_id]; // TODO 字段是否需要校验一下
                $row['user_id'] = $user_id; // 不要遗漏
                $rows[] = $row;
                $this->insertGetId($row); // TODO 字段排查
            }
            //$this->model->insert($rows); // 批量插入
        }
        if ($comm_ids) {
            // 需要更新的
            foreach ($comm_ids as $work_id) {
                $row = $data_list_row[$work_id]; //
                if (isset($row['id'])) unset($row['id']); // 参数上携带的id不可信 TODO 可以从数据库获取相关id
                if (isset($row['user_id'])) unset($row['user_id']);
                if (isset($row['language_id'])) unset($row['language_id']);

                //if (isset($row['id']))
                //    $this->updateData($row['id'], $row); // 更新
                //else {
                    $this->updateDataByUserIdLanguageId($user_id, $work_id, $row);
                //}
            }
        }
        return true;
    }
}
