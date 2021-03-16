<?php

namespace App\Repositories\Admin;

use App\Models\Admin\Language;
use App\Repositories\BaseRepository;

class LanguageRepositoryImpl extends BaseRepository
{
    protected $model ;

    public function __construct() {
        $this->model = new Language();
    }

    public function getList($params, $field = ['*']) {
        $page = isset($params['page']) ? $params['page'] : 1;
        $limit = (isset($params['limit']) && $params['limit'] > 0) ? $params['limit'] : parent::PAGE_SIZE;
        //if ($limit > parent::PAGE_SIZE_MAX) $limit = parent::PAGE_SIZE; // 是否限制最大数量

        $builder = $this->model;
        if (isset($params['status'])) {
            $builder = $builder->where('status', $params['status']);
        }
        /*// 其他参数
        if (isset($params['title']) && $params['title']) {
            $builder = $builder->where('title', 'like', '%'.$params['title'].'%');
        }
        if (isset($params['start_time'], $params['end_time']) && $params['start_time'] && $params['end_time']) {
            $builder = $builder->where('start_time', '>=', $params['start_time']);
            $builder = $builder->where('end_time', '<=',$params['end_time']);
        }
        if(isset($params['nowtime']) && $params['nowtime']){
            $builder = $builder->where('start_time','<=',$params['nowtime']);
            $builder = $builder->where('end_time','>=',$params['nowtime']);
        }*/
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

    // 全量（包括各种状态）
    public function getAllLanguage() {
        $db_result = $this->model->get()->toArray();
        if ($db_result)
            return array_column($db_result, null, 'id');
        return $db_result;
    }
}
