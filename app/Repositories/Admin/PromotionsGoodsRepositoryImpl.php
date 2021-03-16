<?php

namespace App\Repositories\Admin;

use App\Models\Admin\PromotionsGoods;
use App\Models\Goods\GoodsInfo;
use App\Models\Goods\GoodsInfoSku;
use App\Repositories\BaseRepository;

class PromotionsGoodsRepositoryImpl extends BaseRepository
{
    protected $model;
    protected $goodsInfoModel;
    protected $goodsInfoSkuModel;

    public function __construct() {
        $this->model = new PromotionsGoods();
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

    // 连表查询，需要获取sku完整信息
    public function getAllPromotionsGoodsSku($status='all') {
        if (!$this->goodsInfoModel) $this->goodsInfoModel = new GoodsInfo();
        if (!$this->goodsInfoSkuModel) $this->goodsInfoSkuModel = new GoodsInfoSku();

        // 连表查询，3张表连表查
        $the_table = $this->model->getTable() ;
        $sku_table = $this->goodsInfoSkuModel->getTable() ;
        $goods_table = $this->goodsInfoModel->getTable();
        $field = [$sku_table . '.*', 'erp_product_id', 'product_name', 'foreign_name', 'internal_name',
            'pic_url','sell_price','product_price','spu',
            'promotion_id'];

        $builder = $this->model::leftJoin($sku_table, $the_table . '.sku','=', $sku_table . '.sku')
            ->leftJoin($goods_table, $sku_table . '.goods_id','=', $goods_table . '.id');

        if (is_numeric($status))
            $db_result = $builder->where($the_table . '.status', $status)->get($field)->toArray();
        else $db_result = $builder->get($field)->toArray();

        // 按照活动id重新拼装，一个活动可能有多个商品
        $rlt = [];
        foreach ($db_result as $item) {
            $rlt[$item['promotion_id']][] = $item;
        }
        return $rlt;
    }

    public function updateDataByPromotionIdSkuId($promotion_id, $sku, $data_arr) {
        $sql_where = [
            'promotion_id' => $promotion_id,
            'sku' => $sku,
        ];
        $data_arr = $this->filterFields4InsertOrUpdate($data_arr);
        if (isset($data_arr['id'])) unset($data_arr['id']);

        // 是唯一
        $db_result = $this->model->where($sql_where)->first();
        if ($db_result) return $db_result->update($data_arr);
        return 0;
    }

    public function insertGoodsMultiByPromotionId($promotion_id, $data_arr, $with_create_time='', $with_creator_id=0) {
        $insert_arr = [];
        $row['promotion_id'] = $promotion_id;
        $row['status'] = isset($data_arr['status']) ? $data_arr['status'] : 1; // 默认启用
        $row['created_time'] = $with_create_time ?? date('Y-m-d H:i:s');
        $row['creator_id'] = $with_creator_id ?? 0;
        $row['updator_id']   = $row['creator_id'];
        $row['updated_time'] = $row['created_time'];

        if (!isset($data_arr['promotion_goods']) || !$data_arr['promotion_goods']) return false;

        foreach ($data_arr['promotion_goods'] as $rule_info) {
            $row['sku'] = $rule_info['sku'];
            $insert_arr[] = $row;
        }

        $rlt = $this->model->insert($insert_arr);
        return $rlt;
    }

    public function updateGoodsMultiByPromotionId($promotion_id, $data_arr, $create_time='', $creator_id=0) {
        // 有可能不存在，需要进行insert
        // 检查是否存在，不存在则insert，存在则更新
        if (!isset($data_arr['promotion_goods']))
            return false;
        // 类型转换一下
        if (is_string($data_arr['promotion_goods'])) {
            $data_arr['promotion_goods'] = json_decode($data_arr['promotion_goods'], true);
        }
        if (!is_array($data_arr['promotion_goods'])) return [];

        $exits_db = $this->model->where('promotion_id', $promotion_id)->get()->toArray();
        if (!$exits_db) {
            // 无数据则直接插入
            $this->insertGoodsMultiByPromotionId($promotion_id, $data_arr, $create_time, $creator_id);
            return true;
        }

        $sku_ids_db = array_column($exits_db, 'sku');
        $data_list = $data_arr['promotion_goods'];
        $sku_ids_new = array_column($data_list, 'sku');

        // 批量换一下，删除旧的不存在于新数组中的；新增存在于新的不在旧数组中的
        $delete_ids = array_diff($sku_ids_db, $sku_ids_new); // 需要删除的
        $add_ids = array_diff($sku_ids_new, $sku_ids_db); // 需要新增的
        $comm_ids = array_intersect($sku_ids_new, $sku_ids_db); // 交集需要更新

        $row = [];
        $row['promotion_id'] = $promotion_id;
        if ($delete_ids) {
            // 需要删除的
            foreach ($delete_ids as $work_id) {
                $row['sku'] = $work_id;
                $this->model->where($row)->delete(); // TODO 寻找批量删除的方法
            }
        }
        $data_list_row = array_column($data_list, null, 'sku');
        if ($add_ids) {
            // 需要新增的
            foreach ($add_ids as $work_id) {
                $row = $data_list_row[$work_id];     // TODO 字段是否需要校验一下
                $row['promotion_id'] = $promotion_id;
                $this->insertGetId($row);
            }
        }
        if ($comm_ids) {
            // 需要更新的
            foreach ($comm_ids as $work_id) {
                $row = $data_list_row[$work_id]; //
                if (isset($row['id'])) unset($row['id']); // 参数上携带的id不可信
                if (isset($row['promotion_id'])) unset($row['promotion_id']); // 测试的时候有脏数据，强制删除
                if (isset($row['sku'])) unset($row['sku']);

                $this->updateDataByPromotionIdSkuId($promotion_id, $work_id, $row);
            }
        }
        return true;
    }

}
