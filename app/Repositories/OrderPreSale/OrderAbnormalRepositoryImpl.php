<?php

namespace App\Repositories\OrderPreSale;

use App\Models\OrderPreSale\OrderAbnormal;
use App\Repositories\BaseRepository;
use App\Models\OrderPreSale\Order;

class OrderAbnormalRepositoryImpl extends BaseRepository
{
    protected $model ;
    protected $orderModel ;

    public function __construct() {
        $this->model = new OrderAbnormal();
        $this->orderModel = new Order();
    }

    public function getList($params, $field = ['*']) {
        $page = isset($params['page']) ? $params['page'] : 1;
        $limit = (isset($params['limit']) && $params['limit'] > 0) ? $params['limit'] : parent::PAGE_SIZE;
        //if ($limit > parent::PAGE_SIZE_MAX) $limit = parent::PAGE_SIZE; // 是否限制最大数量

        // 客户名称，模糊查询
        if (isset($_REQUEST['customer_name']) && $_REQUEST['customer_name'])
            $params['customer_name'] = ['like', '%'. ($_REQUEST['customer_name']) . '%'];

        // 连表查询
        $the_table = $this->model->getTable() ;
        $order_table = $this->orderModel->getTable();
        $tbl_order_fields = parent::getTheTableFields($this->orderModel);

        $builder = $this->model::leftJoin($order_table, $the_table . '.order_id','=', $order_table . '.id');

        // 自动获取表字段，并自动拼装查询条件
        $params = array_filter($params, function($v){return $v !== '';}); // 空字符不参与搜索条件
        $tbl_fields_arr = parent::getTheTableFields();
        if ($tbl_fields_arr) {
            foreach ($params as $l_field => $val) {
                if (in_array($l_field, $tbl_fields_arr)) {
                    // 针对不同的数据类型，自动拼装
                    $builder = parent::joinTableBuild($builder, $val, $l_field, $the_table);
                } else if (in_array($l_field, $tbl_order_fields)) {
                    $builder = parent::joinTableBuild($builder, $val, $l_field, $order_table);
                }
            }
        }

        $builder = $builder->orderBy($the_table . '.id', 'DESC');
        return $this->pager($builder, $page, $limit, $field);
    }

    // 通过id获取信息
    public function getInfoById($id) {
        $db_result = $this->model->where('order_id', $id)->first();
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
            'order_id' => $id,
        ];
        $data_arr = $this->filterFields4InsertOrUpdate($data_arr);
        if (isset($data_arr['order_id'])) unset($data_arr['order_id']);

        $db_result = $this->model->where($sql_where)->first();
        if ($db_result) return $db_result->update($data_arr);
        return 0;
    }
}
