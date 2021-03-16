<?php

namespace App\Repositories\OrderPreSale;

use App\Models\OrderPreSale\Order;
use App\Models\OrderPreSale\OrderOptRecord;
use App\Repositories\BaseRepository;

class OrderOptRecordRepositoryImpl extends BaseRepository
{
    protected $model;
    protected $orderModel;

    public function __construct() {
        $this->model = new OrderOptRecord();
    }

    public function getList($params, $field = ['*']) {
        $page = isset($params['page']) ? $params['page'] : 1;
        $limit = (isset($params['limit']) && $params['limit'] > 0) ? $params['limit'] : parent::PAGE_SIZE;
        //if ($limit > parent::PAGE_SIZE_MAX) $limit = parent::PAGE_SIZE; // 是否限制最大数量

        if (!isset($params['optator_id'])) $params['optator_id'] = ['>', -1];    // @2020.5.11 puqingyu将不需要显示的操作设置为负值

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

        $builder = $builder->orderBy($params['order_field'] ?? 'id', $params['order_sequence'] ?? 'DESC');
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

    // 通过 order_id，获取订单操作记录，1个订单可能有多个操作记录，需要拼装一下；返回三维数组
    public function getOrderOptRecordsByOrderId($order_id) {
        $db_result = $this->model->where('order_id', $order_id)->orderBy('id', 'desc')->get()->toArray();
        return $db_result;
    }

    // 记录操作日志
    public function insertOrderOptRecord($order_id, $data_arr) {
        if (!isset($data_arr['opt_type_id']) || !isset($data_arr['optator_id'])) return 0;
        try {
            // 记录订单操作记录
            $data_arr['order_id'] = $order_id;
            //$data_arr['opt_type_id'] = 1;
            //$data_arr['optator_id'] = 1;
            if (!isset($data_arr['month'])) $data_arr['month'] = date('Ym'); // TODO 下单时间
            if (!isset($data_arr['remark'])) $data_arr['remark'] = ''; // 数据没有默认值
            $pre_opt_id = $this->insertGetId($data_arr);
            if ($pre_opt_id) {
                $update_arr = [
                    'pre_opt_type'   => $data_arr['opt_type_id'],
                    //'pre_opt_remark' => $data_arr['remark'],
                    'pre_opt_time'   => $data_arr['pre_opt_time'] ?? date('Y-m-d H:i:s'),
                ];
                if (!$this->orderModel) $this->orderModel = new Order();
                $this->orderModel->find($order_id)->update($update_arr);
            }
        } catch (\Exception $e) {
            return 0;
        }
        return 1;
    }

}
