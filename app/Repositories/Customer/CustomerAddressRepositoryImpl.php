<?php

namespace App\Repositories\Customer;

use App\Models\Customer\Customer;
use App\Models\Customer\CustomerAddress;
use App\Repositories\BaseRepository;
use Illuminate\Support\Facades\DB;

class CustomerAddressRepositoryImpl extends BaseRepository
{
    protected $model ;
    protected $customerModel;

    public function __construct() {
        $this->model = new CustomerAddress();
    }

    public function getList($params, $field = ['*']) {
        $page = isset($params['page']) ? $params['page'] : 1;
        $limit = (isset($params['limit']) && $params['limit'] > 0) ? $params['limit'] : parent::PAGE_SIZE;
        //if ($limit > parent::PAGE_SIZE_MAX) $limit = parent::PAGE_SIZE; // 是否限制最大数量


        //if (isset($params['after_sale_id'])) {
            // 涉及到连表查询
            $the_table = $this->model->getTable() ;
            if (!$this->customerModel) $this->customerModel = new Customer();
            $customer_table = $this->customerModel->getTable();
            $tbl_customer_fields = parent::getTheTableFields($this->customerModel);

            $builder = $this->model::leftJoin($customer_table, $the_table . '.customer_id','=', $customer_table . '.id');

            // 获取某客户的地址
            if (isset($params['customer_id'])) {
                $builder = $builder->where($the_table. '.customer_id', $params['customer_id']);
            }

            // 自动获取表字段，并自动拼装查询条件
            $params = array_filter($params, function($v){return $v !== '';}); // 空字符不参与搜索条件
            $tbl_fields_arr = parent::getTheTableFields();
            if ($tbl_fields_arr) {
                foreach ($params as $l_field => $val) {
                    if (in_array($l_field, $tbl_fields_arr)) {
                        // 针对不同的数据类型，自动拼装
                        $builder = parent::joinTableBuild($builder, $val, $l_field, $the_table);
                    } else if (in_array($l_field, $tbl_customer_fields)) {
                        $builder = parent::joinTableBuild($builder, $val, $l_field, $customer_table);
                    }
                }
            }

            $builder = $builder->orderBy($the_table . '.id', 'desc');
        /*} else {
            $builder = $this->model;

            // 获取某客户的地址
            if (isset($params['customer_id'])) {
                $builder = $builder->where('customer_id', $params['customer_id']);
            }

            // 自动获取表字段，并自动拼装查询条件
            $params = array_filter($params, function ($v) {
                return $v !== '';
            }); // 空字符不参与搜索条件
            $tbl_fields_arr = parent::getTheTableFields();
            if ($tbl_fields_arr) {
                foreach ($params as $l_field => $val) {
                    if (in_array($l_field, $tbl_fields_arr)) {
                        // 针对不同的数据类型，自动拼装
                        $builder = parent::joinTableBuild($builder, $val, $l_field);
                    }
                }
            }

            $builder = $builder->orderBy('id', 'desc');
        }*/
        $field = [
            $customer_table.'.*',
            $the_table.'.*',        // 后面的覆盖前面的
            //DB::raw('customer_address.id')
        ];
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

    // 插入数据拼装，数据源为售前手工下单数据
    public function formatDataForInsertByManualOrder($customer_id, $request) {
        $data_arr = [
            'customer_id' => $customer_id,
            'customer_name' => $request['customer_name'] ?? '',
            'language_id' => $request['language_id']??0,
            'country_id' => $request['country_id']??0,      // 是否存国家二字码
            'zone_prov_name' => $request['zone_prov_name']??'',
            'zone_city_name' => $request['zone_city_name']??'',
            'zone_area_name' => $request['zone_area_name']??'',
            'tel' => $request['tel'],
            'address' => $request['address'] ?? '',
            'zip_code' => $request['zip_code']?? '',
            'email' => $request['email'] ?? '',
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
            'tel' => $tel,
            'country_id' => $country_id,
        ];
        $db_result = $this->model->where($sql_where)->first();
        if (!$db_result)
            return $db_result;
        return $db_result->toArray();
    }

    // 检查客户地址是否完整匹配，
    public function getInfoByMultiFields($data_arr) {
        // ALTER TABLE `crm`.`customer` ADD INDEX `tel_country` (`tel`, `country_id`);
        // ALTER TABLE `crm`.`customer_address` ADD INDEX `tel_country` (`tel`, `country_id`);
        $sql_where = [
            'tel'           => $data_arr['tel'],
            'country_id'    => $data_arr['country_id'],
            'customer_id'   => $data_arr['customer_id'],
            //'language_id' => $data_arr['language_id'] ?? '',
            'zone_prov_name' => $data_arr['zone_prov_name'] ?? '',
            'zone_city_name' => $data_arr['zone_city_name'] ?? '',
            'zone_area_name' => $data_arr['zone_area_name'] ?? '',
            'address'        => $data_arr['address'] ?? '',
            'zip_code'      => $data_arr['zip_code'] ?? '',
            //'email'         => $data_arr['email'],
        ];
        if (isset($data_arr['customer_name'])) $sql_where['customer_name'] = $data_arr['customer_name'];
        $db_result = $this->model->where($sql_where)->first();
        if (!$db_result)
            return $db_result;
        return $db_result->toArray();
    }

}
