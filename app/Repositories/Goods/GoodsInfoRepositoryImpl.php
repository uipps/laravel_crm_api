<?php

namespace App\Repositories\Goods;

use App\Models\Goods\GoodsInfo;
use App\Models\Goods\GoodsInfoSku;
use App\Repositories\BaseRepository;

class GoodsInfoRepositoryImpl extends BaseRepository
{
    protected $model ;
    protected $goodsInfoSkuModel;

    public function __construct() {
        $this->model = new GoodsInfo();
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

        if (isset($_REQUEST['product_name']))
            $params['product_name'] = ['like', '%'. ($_REQUEST['product_name']) . '%'];

        if (isset($_REQUEST['spu']))
            $params['spu'] = ['like', '%'. ($_REQUEST['spu']) . '%'];

        // 自动获取表字段，并自动拼装查询条件
        $params = array_filter($params, function($v){return $v !== '';}); // 空字符不参与搜索条件
        if (!isset($_REQUEST['sku']) || '' === $_REQUEST['sku']) {
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
        } else {
            // sku搜索, 需要连表查询
            $params['sku'] = ['like', '%'. ($_REQUEST['sku']) . '%'];   //

            $the_table = $this->model->getTable() ;
            if (!$this->goodsInfoSkuModel) $this->goodsInfoSkuModel = new GoodsInfoSku();
            $sku_table = $this->goodsInfoSkuModel->getTable();
            $tbl_sku_fields = parent::getTheTableFields($this->goodsInfoSkuModel);

            $builder = $this->model::leftJoin($sku_table, $the_table . '.id','=', $sku_table . '.goods_id');

            // 自动获取表字段，并自动拼装查询条件
            $tbl_fields_arr = parent::getTheTableFields();
            if ($tbl_fields_arr) {
                foreach ($params as $l_field => $val) {
                    if (in_array($l_field, $tbl_fields_arr)) {
                        // 针对不同的数据类型，自动拼装
                        $builder = parent::joinTableBuild($builder, $val, $l_field, $the_table);
                    } else if (in_array($l_field, $tbl_sku_fields)) {
                        $builder = parent::joinTableBuild($builder, $val, $l_field, $sku_table);
                    }
                }
            }
            $builder = $builder->orderBy($the_table . '.id', 'desc')->distinct('goods_info.id');
            $field = [$the_table.'.*'];
        }

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

    // 通过erp_product_id获取单条
    public function getInfoByErpProductId($erp_product_id) {
        $db_result = $this->model->where('erp_product_id', $erp_product_id)->first();
        if (!$db_result)
            return $db_result;
        return $db_result->toArray();
    }
}
