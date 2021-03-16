<?php

namespace App\Repositories\OrderPreSale;

use App\Models\OrderPreSale\Order;
use App\Models\OrderPreSale\OrderAudit;
use App\Repositories\BaseRepository;

class OrderAuditRepositoryImpl extends BaseRepository
{
    protected $model ;
    protected $orderModel ;

    public function __construct() {
        $this->model = new OrderAudit();
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
        if (isset($data_arr['id'])) unset($data_arr['id']);

        $db_result = $this->model->where($sql_where)->first();
        if ($db_result) return $db_result->update($data_arr);
        return 0;
    }

    // 批量插入
    public function insertMultipleAuditByPreSaleId($sale_detail, $order_info_list, $create_time, $creator_id) {
        if (!$order_info_list) return 1;
        $sale_id = $sale_detail['id'];

        $data_arr = [];
        foreach ($order_info_list as $l_order_info) {
            if (!$l_order_info || !isset($l_order_info['order_no'])) continue;

            $row = []; // 拼装单条数据
            $row['audit_user_id'] = $sale_id;
            $row['part'] = $row['audit_user_id'] % 10;
            $row['pre_distribute_id'] = $sale_id;           // TODO 前置分配记录id
            $row['department_id'] = $sale_detail['department_id'];
            $row['order_id'] = $l_order_info['id'];
            $row['order_no'] = $l_order_info['order_no'];
            $row['job_type'] = 1;                           // 岗位类别 1售前2售后
            $row['status'] = 0;                             // 状态 0无效1有效
            $row['audit_status'] = 0;                       // 审核状态 0未审核1已审核-1已驳回
            $row['audit_result_id'] = 1;
            $row['created_time'] = $create_time;
            $row['updated_time'] = $row['created_time'];
            $row['creator_id'] = $creator_id;
            $row['updator_id'] = $row['creator_id'];

            //$row = $this->filterFields4InsertOrUpdate($row); // 去掉多余字段
            $data_arr[] = $row;
        }
        return $this->model->insert($data_arr);
    }

    // 单条插入
    public function insertAuditByPreSaleId($sale_detail, $l_order_info, $create_time, $creator_id, $repeat_flag = 0) {
        if (!$l_order_info || !isset($l_order_info['order_no'])) return 1;
        $sale_id = $sale_detail['id'];

        $row = []; // 拼装单条数据
        $row['audit_user_id'] = $sale_id;
        $row['part'] = $row['audit_user_id'] % 10;
        $row['pre_distribute_id'] = $sale_id;           // TODO 前置分配记录id
        $row['department_id'] = $sale_detail['department_id'];
        $row['order_id'] = $l_order_info['id'];
        $row['order_no'] = $l_order_info['order_no'];
        $row['job_type'] = 1;                           // 岗位类别 1售前2售后
        $row['status'] = 0;                             // 状态 0无效1有效
        $row['audit_status'] = 0;                       // 审核状态 0未审核1已审核-1已驳回
        $row['audit_result_id'] = 1;
        $row['created_time'] = $create_time;
        $row['updated_time'] = $row['created_time'];
        $row['creator_id'] = $creator_id;
        $row['updator_id'] = $row['creator_id'];
        $row['repeat_flag'] = $repeat_flag;

        return $this->insertGetId($row);
    }

    public function getInfoByDeptIdOrderId($order_id, $dept_id) {
        $sql_where = [
            'order_id' => $order_id,
            'department_id' => $dept_id,
        ];
        $db_result = $this->model->where($sql_where)->first();
        if (!$db_result)
            return $db_result;
        return $db_result->toArray();
    }

    // 删除单条
    public function delete($id) {
        $sql_where['id'] = $id;
        return $this->model->where($sql_where)->delete();
    }


}
