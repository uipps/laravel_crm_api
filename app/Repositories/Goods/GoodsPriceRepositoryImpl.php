<?php

namespace App\Repositories\Goods;

use App\Models\Goods\GoodsPrice;
use App\Repositories\BaseRepository;

class GoodsPriceRepositoryImpl extends BaseRepository
{
    protected $model ;

    public function __construct() {
        $this->model = new GoodsPrice();
    }

   /*
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
        }

        // 自动获取表字段，并自动拼装查询条件
        $params = array_filter($params, function($v){return $v !== '';}); // 空字符不参与搜索条件
        $tbl_fields_arr = parent::getTheTableFields();
        if ($tbl_fields_arr) {
            if (isset($params['status'])) unset($params['status']); // 前面处理过了
            foreach ($params as $l_field => $val) {
                if (in_array($l_field, $tbl_fields_arr)) {
                    // 针对不同的数据类型，自动拼装
                    $builder = parent::joinTableBuild($builder, $val, $l_field);
                }
            }
        }

        $builder = $builder->orderBy('id', 'asc');
        return $this->pager($builder, $page, $limit, $field);
    }

    // 通过id获取信息
    public function getInfoById($id) {
        $db_result = $this->model->find($id);
        if (!$db_result)
            return $db_result;
        return $db_result->toArray();
    }

    public function updateData($id, $data_arr) {
        $sql_where = [
            'id' => $id,
        ];
        $data_arr = $this->filterFields4InsertOrUpdate($data_arr);
        if (isset($data_arr['id'])) unset($data_arr['id']);

        return $this->model->where($sql_where)->update($data_arr);
    }
    */

    // 新增并返回主键ID
    public function insertGetId($data_arr) {
        $data_arr = $this->filterFields4InsertOrUpdate($data_arr);
        return $this->model->insertGetId($data_arr);
    }

    public function updateDataByGoodsIdCountryId($goods_id, $country_id, $data_arr) {
        $sql_where = [
            'goods_id' => $goods_id,
            'country_id' => $country_id,
        ];
        $data_arr = $this->filterFields4InsertOrUpdate($data_arr);
        if (isset($data_arr['id'])) unset($data_arr['id']);

        // 是唯一
        $db_result = $this->model->where($sql_where)->first();
        if ($db_result) return $db_result->update($data_arr);
        return 0;
    }

    // 拼装一下
    public function getPriceListByGoodsIds($goods_ids) {
        $price_list = $this->model->whereIn('goods_id', $goods_ids)->get()->toArray();
        if (!$price_list)
            return $price_list;
        // 按照goods_id重新组织一下
        $rlt = [];
        foreach ($price_list as $price_info) {
            $rlt[$price_info['goods_id']][] = $price_info;
        }
        return $rlt;
    }

    // 插入price数据
    public function insertPriceMultiByGoodsId($goods_id, $data_arr, $with_create_time='', $with_creator_id=0) {
        $insert_arr = [];
        $row['goods_id'] = $goods_id;
        $row['created_time'] = $with_create_time ?? date('Y-m-d H:i:s');
        $row['creator_id'] = $with_creator_id ?? 0;
        //$row['updator_id']   = $row['creator_id'];            // 数据表没有这2个字段，带上会报错
        //$row['updated_time'] = $row['created_time'];
        //$row['status'] = isset($data_arr['status']) ? $data_arr['status'] : 1; // 默认启用

        if (!isset($data_arr['price_list']) || !$data_arr['price_list']) return false;

        foreach ($data_arr['price_list'] as $price_info) {
            $row['country_id'] = $price_info['country_id'];
            $row['price'] = $price_info['price'];
            $insert_arr[] = $row;
        }

        return $this->model->insert($insert_arr);
    }

    // 更新price
    public function updatePriceMultiByGoodsId($goods_id, $data_arr, $create_time='', $creator_id=0) {
        // 有可能不存在，需要进行insert
        // 检查是否存在，不存在则insert，存在则更新
        if (!isset($data_arr['price_list']))
            return false;
        // 类型转换一下
        if (is_string($data_arr['price_list'])) {
            $data_arr['price_list'] = json_decode($data_arr['price_list'], true);
        }
        if (!is_array($data_arr['price_list'])) return [];

        $exits_db = $this->model->where('goods_id', $goods_id)->get()->toArray(); // 角色所有权限, toArray()返回数组，可能是空数组
        if (!$exits_db) {
            // 无数据则直接插入
            $this->insertPriceMultiByGoodsId($goods_id, $data_arr, $create_time, $creator_id);
            return true;
        }

        $country_ids_db = array_column($exits_db, 'country_id');
        $country_ids_new = array_column($data_arr['price_list'], 'country_id');

        // 批量换一下，删除旧的不存在于新数组中的；新增存在于新的不在旧数组中的
        $delete_ids = array_diff($country_ids_db, $country_ids_new); // 需要删除的
        $add_ids = array_diff($country_ids_new, $country_ids_db); // 需要新增的
        $comm_ids = array_intersect($country_ids_new, $country_ids_db); // 交集需要更新

        $row = [];
        $row['goods_id'] = $goods_id;
        if ($delete_ids) {
            // 需要删除的
            foreach ($delete_ids as $work_id) {
                $row['country_id'] = $work_id;
                $this->model->where($row)->delete();
            }
        }
        $data_list_row = array_column($data_arr['price_list'], null, 'country_id');
        if ($add_ids) {
            // 需要新增的
            //$rows = [];
            foreach ($add_ids as $work_id) {
                $row = $data_list_row[$work_id];
                $row['goods_id'] = $goods_id; // 不要遗漏
                $row['created_time'] = $create_time ?? date('Y-m-d H:i:s');
                $row['creator_id'] = $creator_id ?? 0;
                //$rows[] = $row;
                $this->insertGetId($row);
            }
            //$this->model->insert($rows); // 批量插入
        }
        if ($comm_ids) {
            // 需要更新的
            foreach ($comm_ids as $work_id) {
                $row = $data_list_row[$work_id]; //
                if (isset($row['id'])) unset($row['id']); // 参数上携带的id不可信 TODO 可以从数据库获取相关id
                if (isset($row['goods_id'])) unset($row['goods_id']);
                if (isset($row['country_id'])) unset($row['country_id']);

                $this->updateDataByGoodsIdCountryId($goods_id, $work_id, $row);
            }
        }
        return true;
    }

}
