<?php

namespace App\Repositories\Goods;

use App\Models\Goods\GoodsInfo;
use App\Models\Goods\GoodsInfoSku;
use App\Models\Goods\GoodsPrice;
use App\Repositories\BaseRepository;

class GoodsInfoSkuRepositoryImpl extends BaseRepository
{
    protected $model;
    protected $goodsInfoModel;
    protected $goodsPriceModel;

    public function __construct() {
        $this->model = new GoodsInfoSku();
        $this->goodsInfoModel = new GoodsInfo();
        $this->goodsPriceModel = new GoodsPrice();
    }

    public function getList($params, $field = ['*']) {
        $page = isset($params['page']) ? $params['page'] : 1;
        $limit = (isset($params['limit']) && $params['limit'] > 0) ? $params['limit'] : parent::PAGE_SIZE;
        //if ($limit > parent::PAGE_SIZE_MAX) $limit = parent::PAGE_SIZE; // 是否限制最大数量

        if (!isset($params['status'])) {
            $params['status'] = 1;
        }
        if (isset($_REQUEST['inner_name'])) $params['internal_name'] = ['like', '%' . $_REQUEST['inner_name'] . '%']; // 搜索字段映射
        if (isset($_REQUEST['internal_name'])) $params['internal_name'] = ['like', '%' . $_REQUEST['internal_name'] . '%']; // 搜索字段映射

        // 连表查询
        $the_table = $this->model->getTable() ;
        $goods_table = $this->goodsInfoModel->getTable();
        $goods_price_table = $this->goodsPriceModel->getTable();
        $tbl_goods_fields = parent::getTheTableFields($this->goodsInfoModel);
        $field = [$the_table . '.*',
            $goods_table.'.erp_product_id', $goods_table.'.product_name', $goods_table.'.foreign_name', $goods_table.'.internal_name',
            $goods_table.'.pic_url', $goods_table.'.sell_price', $goods_table.'.spu'];

        $builder = $this->model::leftJoin($goods_table, $the_table . '.goods_id','=', $goods_table . '.id');

        if (isset($params['country_id'])) {
            $builder = $builder->leftJoin($goods_price_table, $goods_price_table . '.goods_id','=', $goods_table . '.id');
            $builder = parent::joinTableBuild($builder, $params['country_id'], 'country_id', $goods_price_table);

            // 返回字段
            $field[] = $goods_price_table.'.price as product_price';
            $field[] = $goods_price_table.'.country_id';
        } else $field[] = 'product_price';

        // 内部名或sku（手工单选择商品的时候）
        if (isset($_REQUEST['fuzzy'])) {
            $builder = $builder->Where($goods_table.'.internal_name', 'like', '%'.$_REQUEST['fuzzy']. '%')
                ->OrWhere($the_table.'.sku', 'like', '%'.$_REQUEST['fuzzy']. '%');

            if(isset($params['internal_name'])) unset($params['internal_name']);
            if(isset($params['sku'])) unset($params['sku']);
        }

        // 自动获取表字段，并自动拼装查询条件
        $params = array_filter($params, function($v){return $v !== '';}); // 空字符不参与搜索条件
        $tbl_fields_arr = parent::getTheTableFields();
        if ($tbl_fields_arr) {
            foreach ($params as $l_field => $val) {
                if (in_array($l_field, $tbl_fields_arr)) {
                    // 针对不同的数据类型，自动拼装
                    $builder = parent::joinTableBuild($builder, $val, $l_field, $the_table);
                }
                // 状态筛选条件，两边都有状态的筛选
                if (in_array($l_field, $tbl_goods_fields)) {
                    $builder = parent::joinTableBuild($builder, $val, $l_field, $goods_table);
                }
            }
        }

        $builder = $builder->orderBy($the_table . '.id', 'DESC');
        return $this->pager($builder, $page, $limit, $field);
    }

    // 通过sku_id获取信息
    public function getInfoById($id) {
        // 连表查询
        $the_table = $this->model->getTable() ;
        $goods_table = $this->goodsInfoModel->getTable();
        $field = [$the_table . '.*', 'erp_product_id', 'product_name', 'foreign_name', 'internal_name',
            'pic_url','sell_price','product_price','spu'];

        $builder = $this->model::leftJoin($goods_table, $the_table . '.goods_id','=', $goods_table . '.id');
        $builder = parent::joinTableBuild($builder, $id, 'id',  $the_table);

        $db_result = $builder->get($field)->first();
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

    // 插入sku数据
    public function insertSkuMultiByGoodsId($goods_id, $data_arr, $with_create_time='', $with_creator_id=0) {
        $insert_arr = [];
        $row['goods_id'] = $goods_id;
        $row['status'] = isset($data_arr['status']) ? $data_arr['status'] : 1; // 默认启用
        $row['created_time'] = $with_create_time ?? date('Y-m-d H:i:s');
        $row['creator_id'] = $with_creator_id ?? 0;
        //$row['updator_id']   = $row['creator_id'];            // 数据表没有这2个字段，带上会报错
        //$row['updated_time'] = $row['created_time'];

        if (!isset($data_arr['sku_list']) || !$data_arr['sku_list']) return false;

        foreach ($data_arr['sku_list'] as $sku_info) {
            $row['erp_sku_id'] = $sku_info['id_product_sku'];
            $row['sku'] = $sku_info['model'] . $sku_info['sku'];
            $row['option_values'] = json_encode($sku_info);
            $insert_arr[] = $row;
        }

        return $this->model->insert($insert_arr);
    }

    // 通过sku名称列表获取批量信息
    public function getInfosBySkuList($sku_list) {
        // 连表查询
        $the_table = $this->model->getTable() ;
        $goods_table = $this->goodsInfoModel->getTable();
        $field = [$the_table . '.*', 'erp_product_id', 'product_name', 'foreign_name', 'internal_name',
            'pic_url','sell_price','product_price','spu'];

        $builder = $this->model::leftJoin($goods_table, $the_table . '.goods_id','=', $goods_table . '.id');
        $builder = parent::joinTableBuild($builder, ['in', $sku_list], 'sku',  $the_table);

        return $builder->get($field)->toArray();
    }

    public function getListByGoodsId($goods_id) {
        return $this->model->where('goods_id', $goods_id)->get()->toArray();
    }

    // 拼装一下
    public function getListByGoodsIds($goods_ids) {
        $sku_list = $this->model->whereIn('goods_id', $goods_ids)->get()->toArray();
        if (!$sku_list)
            return $sku_list;
        // 按照goods_id重新组织一下
        $rlt = [];
        foreach ($sku_list as $sku_info) {
            $rlt[$sku_info['goods_id']][] = $sku_info;
        }
        return $rlt;
    }

    public function updateSkuMultiByGoodsId($goods_id, $data_arr, $create_time='', $creator_id=0) {
        // 有可能不存在，需要进行insert
        // 检查是否存在，不存在则insert，存在则更新
        if (!isset($data_arr['sku_list']))
            return false;
        // 类型转换一下
        if (is_string($data_arr['sku_list'])) {
            $data_arr['sku_list'] = json_decode($data_arr['sku_list'], true);
        }
        if (!is_array($data_arr['sku_list'])) return [];

        $exits_db = $this->model->where('goods_id', $goods_id)->get()->toArray(); // 角色所有权限, toArray()返回数组，可能是空数组
        if (!$exits_db) {
            // 无数据则直接插入
            $this->insertSkuMultiByGoodsId($goods_id, $data_arr, $create_time, $creator_id);
            return true;
        }

        $sku_db = array_column($exits_db, 'sku');           // 包括
        $sku_new = [];
        $data_list_row = [];
        foreach ($data_arr['sku_list'] as $row) {
            //if (0 == $row['status']) continue;              // 前端会提示，只要提交过来了，就表示设置有效
            $sku_str = $row['model'] . $row['barcode'];   // 拼装sku
            $sku_new[] = $sku_str;
            $data_list_row[$sku_str] = $row;
        }

        // 批量换一下，删除旧的不存在于新数组中的；新增存在于新的不在旧数组中的
        $add_ids = array_diff($sku_new, $sku_db);       // 需要新增的
        $update_skus = [];
        $delete_skus = [];
        foreach ($exits_db as $row) {
            // 提交的sku数据都是需要变成有效的，同数据库中的sku进行校对，数据库中sku状态分停用、启用
            // 停用状态的如果在提交数据中，则需要更新状态为启用；启用状态的如果不在提交中，则需要改成停用状态
            if (in_array($row['sku'], $sku_new)) {
                if (0 == $row['status'])
                    $update_skus[] = $row['sku'];
            } else {
                $delete_skus[] = $row['sku'];
            }
        }

        if ($delete_skus) {
            // 需要删除的，软删除
            foreach ($delete_skus as $work_id) {
                $row = [];
                //$row['goods_id'] = $goods_id;
                $row['sku'] = $work_id;
                $this->model->where($row)->update(['status'=>0]);
            }
        }
        if ($add_ids) {
            // 需要新增的
            foreach ($add_ids as $work_id) {
                $row = $data_list_row[$work_id];
                $row = self::formatInsertData($row, $goods_id, $create_time, $creator_id);
                $this->insertGetId($row);
            }
        }
        if ($update_skus) {
            // 需要更新为启用
            foreach ($update_skus as $work_id) {
                $row = [];
                $row['sku'] = $work_id;
                $this->model->where($row)->update(['status'=>1]);
            }
        }
        return true;
    }

    // 将插入前的数据格式化, 特别是option_values
    public function formatInsertData($row, $goods_id, $create_time, $creator_id) {
        if (isset($row['id'])) unset($row['id']);
        $option_values = [
            'inner_name' => '',
            'id_product_sku' => '',
            'id_product' => '',
            'sku' => '',
            'model' => '',
            'barcode' => '',
            'option_value' => '',
            'status' => 1,
            'title' => '',
            'id_department' => '',
            'purchase_price' => '',
            'weight' => '',
            'oldsku' => '',
        ];
        $option_values = array_merge($option_values, $row);
        $option_values['sku'] = str_replace($option_values['model'], '', $option_values['sku']);
        $row['goods_id'] = $goods_id; // 不要遗漏
        $row['erp_sku_id'] = $row['id_product_sku'];
        $row['sku'] = $option_values['model'] . $option_values['sku'];
        $row['status'] = 1;
        $row['option_values'] = json_encode($option_values);
        $row['creator_id'] = $creator_id;
        $row['created_time'] = $create_time;

        return $row;
    }
}
