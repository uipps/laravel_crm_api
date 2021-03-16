<?php

namespace App\Repositories\Admin;

use App\Models\Admin\PromotionsGoods;
use App\Models\Goods\GoodsInfo;
use App\Models\Admin\Promotions;
use App\Repositories\BaseRepository;

class PromotionsRepositoryImpl extends BaseRepository
{
    protected $model ;

    public function __construct() {
        $this->model = new Promotions();
    }

    public function getList($params, $field = ['*']) {
        $page = isset($params['page']) ? $params['page'] : 1;
        $limit = (isset($params['limit']) && $params['limit'] > 0) ? $params['limit'] : parent::PAGE_SIZE;
        //if ($limit > parent::PAGE_SIZE_MAX) $limit = parent::PAGE_SIZE; // 是否限制最大数量

        $builder = $this->model;
        $builder2 = $this->model;
        if (!isset($params['status'])) {
            $builder = $builder->where('status', '!=', -1); // 只需要返回非删除状态的活动
            $builder2 = $builder2->where('status', '!=', -1);
        }

        // 活动名称，模糊查询
        if (isset($_REQUEST['name']) && $_REQUEST['name'])
            $params['name'] = ['like', '%'. ($_REQUEST['name']) . '%'];
        // 活动商品，模糊查询，先搜商品，再查sku范围，然后再连表查活动商品
        // 针对商品名称搜索，internal_name ，如果有商品名称参与搜索，先查询商品名称是否存在于我们的商品库中
        //   1. 不存在于商品库，则直接返回空
        //   2.   2.1） 存在于商品库，但是在部分商品的活动中不存在，则只返回全部商品 goods_scope=1 的
        //        2.2） 存在于商品库，但是在部分商品的活动中也存在，则需要合并  goods_scope=1 和 部分  goods_scope=2的
        if (isset($_REQUEST['internal_name']) && $_REQUEST['internal_name']) {
            // 模糊搜索
            $goods_list = GoodsInfo::where('internal_name', 'LIKE', '%'.$_REQUEST['internal_name'].'%' )->get()->toArray();
            if ($goods_list) {
                // 是否存在于活动商品库
                $builder_pgoods = new PromotionsGoods();
                foreach ($goods_list as $goods_info) {
                    $builder_pgoods = $builder_pgoods->orWhere('sku', 'LIKE', $goods_info['spu'] . '%');
                }
                $promotion_goods_list = $builder_pgoods->get()->toArray();

                if ($promotion_goods_list) {
                    $promotion_id_list = array_column($promotion_goods_list, 'promotion_id');
                    $promotion_id_list = array_unique($promotion_id_list);  // 去重
                    $params['promotion_id_list'] = $promotion_id_list;

                    // 需要进行合并，获取到活动ID列表，然后进行合并即可，也就是单表查询
                    $params2 = $params;
                    $params2['goods_scope'] = 2;    // 部分商品的情况
                    $params2['id'] = ['IN', $promotion_id_list];

                    $params['goods_scope'] = 1; // 返回了全部商品活动，还要union另外一条语句
                } else {
                    $params['goods_scope'] = 1;  // 只返回全部商品的活动
                }
            } else {
                $params['id'] = ['<', 0];  // 确保搜不到东西，TODO 直接返回，减少sql查询
            }
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

        if (isset($params['promotion_id_list']) && $params['promotion_id_list']) {
            $params2 = array_filter($params2, function($v){return $v !== '';});
            foreach ($params2 as $l_field => $val) {
                if (in_array($l_field, $tbl_fields_arr)) {
                    // 针对不同的数据类型，自动拼装
                    $builder2 = parent::joinTableBuild($builder2, $val, $l_field);
                }
            }
            $builder = $builder->union($builder2);
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

    public function getAllPromotions($type='all') {
        if (is_numeric($type))
            $db_result = $this->model->where('status', $type)->get()->toArray();
        else $db_result = $this->model->get()->toArray();
        if ($db_result)
            return array_column($db_result, null, 'id');
        return $db_result;
    }
}
