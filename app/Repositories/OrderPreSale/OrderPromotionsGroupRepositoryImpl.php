<?php

namespace App\Repositories\OrderPreSale;

use App\Models\OrderPreSale\OrderPromotionsGroup;
use App\Repositories\BaseRepository;

class OrderPromotionsGroupRepositoryImpl extends BaseRepository
{
    protected $model ;

    public function __construct() {
        $this->model = new OrderPromotionsGroup();
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

    public function getAllPromotionsGroup($type=1) {
        $db_result = $this->model->where('type', $type)->get()->toArray();
        if ($db_result)
            return array_column($db_result, null, 'promotion_rule_ids');    // 可能逗号分隔的2,3，大部分都是单规则
        return $db_result;
    }

    // 插入新数据
    public function insertMultiByRuleStrs($rule_strs, $create_time, $creator_id) {
        $rows = [];
        foreach ($rule_strs as $rule_str) {
            $row = [];
            $row['promotion_rule_ids'] = $rule_str;
            $row['promotion_type'] = 1; // 当前活动类型就只有一种
            $row['created_time'] = $create_time;
            $row['updated_time'] = $row['created_time'];
            $row['creator_id'] = $creator_id;
            $row['updator_id'] = $row['creator_id'];
            $rows[] = $row;
        }
        return $this->model->insert($rows);
    }
}
