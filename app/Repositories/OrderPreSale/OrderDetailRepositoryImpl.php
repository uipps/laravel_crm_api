<?php

namespace App\Repositories\OrderPreSale;

use App\Models\OrderPreSale\OrderDetail;
use App\Repositories\BaseRepository;

class OrderDetailRepositoryImpl extends BaseRepository
{
    protected $model ;

    public function __construct() {
        $this->model = new OrderDetail();
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

    public function updateDataByOrderIdSkuId($order_id, $sku_id, $data_arr) {
        $sql_where = [
            'order_id' => $order_id,
            'sku' => $sku_id,
        ];
        $data_arr = $this->filterFields4InsertOrUpdate($data_arr);
        if (isset($data_arr['id'])) unset($data_arr['id']);

        // 虽然还有month分区字段，三者唯一
        $db_result = $this->model->where($sql_where)->first();
        if ($db_result) return $db_result->update($data_arr);
        return 0;
    }

    // 通过 order_id 列表，获取商品信息，1个订单可能有多个商品信息，需要拼装一下；返回三维数组
    public function getGoodsListByOrderIds($order_ids) {
        $db_result = $this->model->whereIn('order_id', $order_ids)->get()->toArray();
        if (!$db_result)
            return $db_result;
        // 按照订单id重新组织一下数据，一个订单可能有多个商品
        $rlt = [];
        foreach ($db_result as $row) {
            $rlt[$row['order_id']][] = $row;
        }
        return $rlt;
    }
    public function getGoodsListByOrderId($order_id) {
        $db_result = $this->model->where('order_id', $order_id)->get()->toArray();
        return $db_result;
    }

    public function insertMultipleByOrderId($order_id, $data_arr) {
        if (!$data_arr) return 1;

        foreach ($data_arr as &$row) {
            if ($row['num'] <=0 )
                continue;
            if (isset($row['id'])) unset($row['id']);

            $row['month'] = date('Ym');
            $row['order_id'] = $order_id;
            if (!isset($row['unit_price']))
                $row['unit_price'] = $row['product_price']??0;  // 字段做映射
            $row['total_amount'] = $row['total_amount']??0;
            $row['promotions_amount'] = 0.0;
            $row['finish_amount'] = $row['finish_amount'] ?? 0;
            // option_values 是数组
            if (isset($row['option_values'])) {
                if (is_array($row['option_values']) && $row['option_values'])
                    $row['option_values'] = json_encode($row['option_values']);
                else if (is_array($row['option_values']) && !$row['option_values'])
                    $row['option_values'] = '';
            } else $row['option_values'] = '';

            if (isset($row['rule_ids'])) {
                if (is_array($row['rule_ids']) && $row['rule_ids'])
                    $row['rule_ids'] = json_encode($row['rule_ids']);
                else if (is_array($row['rule_ids']) && !$row['rule_ids'])
                    $row['rule_ids'] = '';
            } else {
                // 有的有rule_ids ，导致字段跟值对不上而报错
                // Insert value list does not match column list: 1136 Column count doesn't match value count at row 2
                $row['rule_ids'] = '';
            }

            $row = $this->filterFields4InsertOrUpdate($row); // 去掉多余字段
        }
        //print_r($data_arr);exit;
        return $this->model->insert($data_arr);
    }

    public function updateMultiGoodsByOrderId($order_id, $data_arr, $create_time='', $creator_id=0) {
        // 有可能不存在，需要进行insert
        // 检查是否存在，不存在则insert，存在则更新
        if (!isset($data_arr['goods_info']) || !$data_arr['goods_info'])
            return true;

        // 类型转换一下
        if (is_string($data_arr['goods_info'])) {
            $data_arr['goods_info'] = json_decode($data_arr['goods_info'], true);
        }
        if (!is_array($data_arr['goods_info'])) return [];

        $exits_db = $this->model->where('order_id', $order_id)->get()->toArray(); // 该订单之前保存的所有商品
        if (!$exits_db) {
            // 无数据则直接插入
            $this->insertMultipleByOrderId($order_id, $data_arr['goods_info']);
            return true;
        }

        $sku_ids_db = array_column($exits_db, 'sku');
        $sku_ids_new = array_column($data_arr['goods_info'], 'sku');

        // 批量换一下，删除旧的不存在于新数组中的；新增存在于新的不在旧数组中的
        $delete_ids = array_diff($sku_ids_db, $sku_ids_new); // 需要删除的
        $add_ids = array_diff($sku_ids_new, $sku_ids_db); // 需要新增的
        $comm_ids = array_intersect($sku_ids_new, $sku_ids_db); // 交集需要更新

        $row = [];
        $row['order_id'] = $order_id;
        if ($delete_ids) {
            // 需要删除的
            foreach ($delete_ids as $work_id) {
                $row['order_id'] = $order_id;
                $row['sku'] = $work_id;
                $this->model->where($row)->delete();
            }
        }
        $data_list_row = array_column($data_arr['goods_info'], null, 'sku'); // 数据可能是不真实，需要重新获取
        if ($add_ids) {
            // 需要新增的
            $rows = [];
            foreach ($add_ids as $work_id) {
                $row = $data_list_row[$work_id]; //
                $row['order_id'] = $order_id; // 不要遗漏
                $rows[] = $row;
            }
            $this->insertMultipleByOrderId($order_id, $rows); // 批量插入
        }
        if ($comm_ids) {
            // 需要更新的
            foreach ($comm_ids as $work_id) {
                $row = $data_list_row[$work_id]; //
                if (isset($row['id'])) unset($row['id']);
                if (isset($row['order_id'])) unset($row['order_id']);
                if (isset($row['sku_id'])) unset($row['sku_id']);
                if (isset($row['sku'])) unset($row['sku']);

                // 活动规则id字段更新
                if (isset($row['rule_ids'])) {
                    if (is_array($row['rule_ids'])) {
                        if ($row['rule_ids']) {
                            $row['rule_ids'] = json_encode($row['rule_ids']);
                        } else {
                            $row['rule_ids'] = '';
                        }
                    }
                }
                if (isset($row['option_values'])) {
                    if (is_array($row['option_values'])) {
                        if ($row['option_values']) {
                            $row['option_values'] = json_encode($row['option_values']);
                        } else {
                            $row['option_values'] = '';
                        }
                    }
                }

                $this->updateDataByOrderIdSkuId($order_id, $work_id, $row);

            }
        }
        return true;
    }

}
