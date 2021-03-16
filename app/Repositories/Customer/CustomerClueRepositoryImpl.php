<?php

namespace App\Repositories\Customer;

use App\Models\Customer\CustomerClue;
use App\Repositories\BaseRepository;

class CustomerClueRepositoryImpl extends BaseRepository
{
    protected $model ;

    public function __construct() {
        $this->model = CustomerClue::userDepartment();
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

        // 客户名称，模糊搜索
        if (isset($_REQUEST['name']) && $_REQUEST['name'])
            $params['name'] = ['like', '%'. ($_REQUEST['name']) . '%'];

        // 社交媒体平台账号，可能是 facebook_id , whatsapp_id , line_id 3者中的一种
        if (isset($params['social_account_type']) && isset($params['social_account']) && $params['social_account']) {
            if ('facebook_id' == $params['social_account_type']) {
                $params['facebook_id'] = ['like', '%'.$params['social_account']. '%'];
            } else if ('whatsapp_id' == $params['social_account_type']) {
                $params['whatsapp_id'] = ['like', '%'.$params['social_account']. '%'];
            } else if ('line_id' == $params['social_account_type']) {
                $params['line_id'] = ['like', '%'.$params['social_account']. '%'];
            } else {
                // 可能是 facebook_id , whatsapp_id , line_id 3者中的一种
                $builder = $builder->Where('facebook_id', 'like', '%'.$params['social_account']. '%')
                    ->OrWhere('whatsapp_id', 'like', '%'.$params['social_account']. '%')
                    ->OrWhere('line_id', 'like', '%'.$params['social_account']. '%');
            }
            unset($params['social_account']);
            unset($params['social_account_type']);
        }

        // 线索客户能看到自己创建的线索 post_sale_id ， creator_id
        /*if (isset($params['post_sale_id']) && isset($params['creator_id']) && isset($params['creator_id'][0]) && 'or'==strtolower($params['creator_id'][0])) {
            $builder = $builder->Where('post_sale_id', $params['post_sale_id'])
                ->OrWhere('creator_id', $params['post_sale_id']);
            unset($params['post_sale_id']);
        }
        if (isset($params['creator_id']) && is_array($params['creator_id'])) unset($params['creator_id']);*/

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
            $rlt_obj = $this->model->find($v_info['id']);
            if ($rlt_obj) {
                $rlt = $rlt_obj->update($data_arr);
                if ($rlt) $num++;
            }
        }
        return $num;
    }

    public function sqlCountCustomerClue($params) {
        $builder = CustomerClue::userDepartment();
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

    // 通过 customer_ids 获取相关线索数据
    public function getListByCustomerIds($customer_ids, $field=['*']) {
        $db_result = $this->model->whereIn('customer_id', $customer_ids)->get($field)->toArray();
        if (!$db_result)
            return $db_result;
        return array_column($db_result, null, 'customer_id');   // 可能有多条，只拿一条即可
    }

}
