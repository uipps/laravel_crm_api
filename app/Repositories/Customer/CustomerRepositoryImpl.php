<?php

namespace App\Repositories\Customer;

use App\Models\Customer\Customer;
use App\Repositories\BaseRepository;

class CustomerRepositoryImpl extends BaseRepository
{
    protected $model ;

    public function __construct() {
        $this->model = new Customer();
    }

    public function getList($params, $field = ['*']) {
        $page = isset($params['page']) ? $params['page'] : 1;
        $limit = (isset($params['limit']) && $params['limit'] > 0) ? $params['limit'] : parent::PAGE_SIZE;
        //if ($limit > parent::PAGE_SIZE_MAX) $limit = parent::PAGE_SIZE; // 是否限制最大数量

        $builder = $this->model;

        if (isset($_REQUEST['customer_name']) && $_REQUEST['customer_name'])
            $params['customer_name'] = ['like', '%'. ($_REQUEST['customer_name']) . '%'];

        // 客户姓名或ID，需要自动判断是id还是名称
        if (isset($_REQUEST['customer_name_id']) && $_REQUEST['customer_name_id']) {
            if (is_numeric($_REQUEST['customer_name_id'])) {
                $params['customer_id'] = $_REQUEST['customer_name_id'];
            } else {
                $params['customer_name'] = ['like', '%'. ($_REQUEST['customer_name_id']) . '%'];
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

    // 通过id列表获取批量数据
    public function getListByIds($id_list) {
        $db_result = $this->model->whereIn('id', $id_list)->get()->toArray();
        if (!$db_result)
            return $db_result;
        return array_column($db_result, null, 'id');
    }

    // 插入数据拼装，数据源为售前手工下单数据
    public function formatDataForInsertByManualOrder($request) {
        $data_arr = [
            'customer_name' => $request['customer_name'],
            'tel' => $request['tel'],
            'customer_key' => md5($request['tel']),
            'country_id' => $request['country_id']??0,
            'language_id' => $request['language_id']??0,
            'pre_sale_id' => $request['pre_sale_id']??0,  // 售前客服-待分配
            'after_sale_id' => $request['after_sale_id']??0,
            'order_num' => 0,       // 默认0，后期需要crontab进行维护？
            'last_contact_time' => parent::DATETIME_NOT_NULL_DEFAULT,
            'source_type' => $request['source_type']??2,        // 来源类别,1广告2咨询3复购
            'quality_level' => $request['quality_level']??0,    // 客户质量 ABCD
            'distribution_status' => 1,     // 0-未分配；1-已分配
            'received_flag' => 0,           // 0-未签收；1-已签收 TODO
        ];
        // 有可能只有修改人和修改时间
        if (isset($request['creator_id'])) {
            $data_arr['created_time'] = $request['created_time'];
            $data_arr['creator_id'] = $request['creator_id'];
        } else if (isset($request['updator_id'])) {
            $data_arr['created_time'] = $request['updated_time'];
            $data_arr['creator_id'] = $request['updator_id'];
        } else {
            $data_arr['created_time'] = $request['created_time'] ?? date('Y-m-d H:i:s');
            $data_arr['creator_id'] = $request['creator_id'] ?? 0;
        }
        $data_arr['updated_time'] = $data_arr['created_time'];
        $data_arr['updator_id'] = $data_arr['creator_id'];
        return $data_arr;
    }

    // 通过国家id和手机号获取客户地址信息，因为一个客户可能有多个地址
    public function getInfoByCountryIdAndTel($country_id, $tel) {
        $sql_where = [
            'country_id' => $country_id,
            'tel' => $tel,
        ];
        $db_result = $this->model->where($sql_where)->first();
        if (!$db_result)
            return $db_result;
        return $db_result->toArray();
    }

    // 通过条件更新数据
    public function updateMultiByCondition($sql_where, $data_arr) {
        $builder = $this->model;
        foreach ($sql_where as $l_field => $val) {
            // 针对不同的数据类型，自动拼装
            $builder = parent::joinTableBuild($builder, $val, $l_field);
        }

        // 逐条更新，才能使用事件监听
        $l_list = $builder->get()->toArray();
        if (!$l_list)
            return 1;
        $num = 0;
        foreach ($l_list as $v_info) {
            $rlt = $this->model->find($v_info['id'])->update($data_arr);
            if ($rlt) $num++;
        }
        return $num;
    }

    public function formatDataForUpdateByManualOrder($request, $create_time='', $creator_id=0) {
        $data_arr = $request;

        if (isset($data_arr['tel'])) $data_arr['customer_key'] = md5($data_arr['tel']);

        // 有可能只有修改人和修改时间
        if (isset($data_arr['creator_id'])) unset($data_arr['creator_id']);
        if (isset($data_arr['created_time'])) unset($data_arr['created_time']);

        if (!isset($data_arr['updator_id'])) $data_arr['updator_id'] = $creator_id;
        if (!isset($data_arr['updated_time'])) $data_arr['updated_time'] = $create_time ? $create_time : date('Y-m-d H:i:s');

        return $data_arr;
    }

    public function sqlCountCustomer($params) {
        $builder = $this->model;
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
        $num = $builder->count();
        return $num;
    }

}
