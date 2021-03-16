<?php

namespace App\Repositories\Admin;

use App\Models\Admin\PromotionsGoodsNumRule;
use App\Repositories\BaseRepository;

class PromotionsGoodsNumRuleRepositoryImpl extends BaseRepository
{
    protected $model ;

    public function __construct() {
        $this->model = new PromotionsGoodsNumRule();
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

    public function getAllPromotionsRules($status='') {
        if (is_numeric($status))
            $db_result = $this->model->where('status', $status)->orderBy('display_order','asc')->orderBy('id','asc')->get()->toArray();
        else $db_result = $this->model->orderBy('display_order','asc')->orderBy('id','asc')->get()->toArray();
        if ($db_result)
            return array_column($db_result, null, 'id');
        return $db_result;
    }

    public function updateDataByPromotionIdMinnumId($promotion_id, $sku, $data_arr) {
        $sql_where = [
            'promotion_id' => $promotion_id,
            'min_num' => $sku,
        ];
        $data_arr = $this->filterFields4InsertOrUpdate($data_arr);
        if (isset($data_arr['id'])) unset($data_arr['id']);

        // 是唯一的
        $db_result = $this->model->where($sql_where)->first();
        if ($db_result) return $db_result->update($data_arr);
        return 0;
    }

    public function insertRuleMultiByPromotionId($promotion_id, $data_arr, $with_create_time='', $with_creator_id=0) {
        $insert_arr = [];
        $row['promotion_id'] = $promotion_id;
        $row['status'] = isset($data_arr['status']) ? $data_arr['status'] : 1; // 默认启用
        $row['created_time'] = $with_create_time ?? date('Y-m-d H:i:s');
        $row['creator_id'] = $with_creator_id ?? 0;
        $row['updator_id']   = $row['creator_id'];
        $row['updated_time'] = $row['created_time'];

        if (!isset($data_arr['promotion_rules']) || !$data_arr['promotion_rules']) return false;

        $display_order = 0; // 显示顺序按照提交过来的顺序
        foreach ($data_arr['promotion_rules'] as $rule_info) {
            $display_order++;
            $row['display_order'] = $display_order;
            $row['min_num'] = $rule_info['min_num'];
            $row['discount'] = $rule_info['discount'];
            $insert_arr[] = $row;
        }

        $rlt = $this->model->insert($insert_arr);
        return $rlt;
    }

    public function updateRuleMultiByPromotionId($promotion_id, $data_arr, $create_time='', $creator_id=0) {
        // 有可能不存在，需要进行insert
        // 检查是否存在，不存在则insert，存在则更新
        if (!isset($data_arr['promotion_rules']))
            return false;
        // 类型转换一下
        if (is_string($data_arr['promotion_rules'])) {
            $data_arr['promotion_rules'] = json_decode($data_arr['promotion_rules'], true);
        }
        if (!is_array($data_arr['promotion_rules'])) return [];

        $exits_db = $this->model->where('promotion_id', $promotion_id)->get()->toArray();
        if (!$exits_db) {
            // 无数据则直接插入
            $this->insertRuleMultiByPromotionId($promotion_id, $data_arr, $create_time, $creator_id);
            return true;
        }

        // 规则改成： 只要有变化，需要有新ID，因前端通过 rule_id 判断是否修改过规则；
        $min_nums_db_arr = self::getminNumDiscount($exits_db);
        $min_nums_db = array_keys($min_nums_db_arr);
        $data_list = $data_arr['promotion_rules'];
        $data_list_row = self::getminNumDiscount($data_list);
        $min_nums_new = array_keys($data_list_row);

        // 批量换一下，删除旧的不存在于新数组中的；新增存在于新的不在旧数组中的
        $delete_ids = array_diff($min_nums_db, $min_nums_new); // 需要删除的
        $add_ids = array_diff($min_nums_new, $min_nums_db); // 需要新增的
        $comm_ids = array_intersect($min_nums_new, $min_nums_db); // 交集需要更新display_order

        $row = [];
        $row['promotion_id'] = $promotion_id;
        if ($delete_ids) {
            // 需要删除的
            foreach ($delete_ids as $work_id) {
                $tmp = explode('-', $work_id);
                $row['min_num'] = $tmp[0];
                $row['discount'] = $tmp[1];
                $this->model->where($row)->delete(); // TODO 寻找批量删除的方法
            }
        }
        //$data_list_row = array_column($data_list, null, 'min_num');
        if ($add_ids) {
            // 需要新增的
            foreach ($add_ids as $work_id) {
                $row = $data_list_row[$work_id];
                //$row['min_num'] = $tmp[0];
                //$row['discount'] = $tmp[1];
                $row['status'] = isset($data_arr['status']) ? $data_arr['status'] : 1; // 默认启用
                $row['created_time'] = $create_time ?? date('Y-m-d H:i:s');
                $row['creator_id'] = $creator_id ?? 0;
                $row['updator_id']   = $row['creator_id'];
                $row['updated_time'] = $row['created_time'];
                $row['promotion_id'] = $promotion_id;
                $this->insertGetId($row);
            }
        }
        if ($comm_ids) {
            // 没有修改的，只需更新 display_order
            foreach ($comm_ids as $work_id) {
                $row = $data_list_row[$work_id];
                $update_arr = [
                    'display_order' => $row['display_order']        // 只需要更新显示顺序字段
                ];
                $this->updateDataByPromotionIdMinDiscount($promotion_id, $row['min_num'], $row['discount'], $update_arr);
            }
        }
        return true;
    }

    // 拼装唯一
    public function getminNumDiscount($data_arr) {
        $rlt = [];
        if (!$data_arr) return $rlt;

        $display_order = 0; // 显示顺序按照提交过来的顺序
        foreach ($data_arr as $l_row) {
            $display_order++;
            $key = $l_row['min_num'] . '-' . bcadd($l_row['discount'], 0, 2);  // min_num-discount 两个字段联合唯一，都确保小数点后2位
            $l_row['display_order'] = $display_order;   // 显示顺序，重新编号
            $rlt[$key] = $l_row;
        }
        return $rlt;
    }

    public function updateDataByPromotionIdMinDiscount($promotion_id, $min_num, $discount, $data_arr) {
        $sql_where = [
            'promotion_id' => $promotion_id,
            'min_num' => $min_num,
            'discount' => $discount,
        ];
        $data_arr = $this->filterFields4InsertOrUpdate($data_arr);
        if (isset($data_arr['id'])) unset($data_arr['id']);

        // 是唯一的
        $db_result = $this->model->where($sql_where)->first();
        if ($db_result) return $db_result->update($data_arr);
        return 0;
    }
}
