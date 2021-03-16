<?php

namespace App\Services\OrderPreSale;

use App\Libs\Utils\ErrorMsg;
use App\Dto\DataListDto;
use App\Dto\ResponseDto;
use App\Dto\OrderCallRecordDto;
use App\Models\Admin\CallAccount;
use App\Models\Admin\CallUserConfig;
use App\Models\OrderPreSale\Order;
use App\Repositories\Admin\CountryRepository;
use App\Repositories\OrderPreSale\OrderCallRecordRepository;
use App\Repositories\Admin\UserRepository;
use App\Repositories\OrderPreSale\OrderRepository;
use App\Services\BaseService;
use Illuminate\Support\Facades\Validator;
use GuzzleHttp\Client;


class OrderCallRecordService extends BaseService
{
    protected $theRepository;
    protected $orderRepository;
    protected $countryRepository;

    public function __construct() {
        $this->theRepository = new OrderCallRecordRepository();
        $this->userRepository = new UserRepository(); // 用于权限检查
    }

    public function getList() {
        $request = request()->all(); // 参数接收
        $responseDto = new ResponseDto();

        //$login_user_info = self::getCurrentLoginUserInfo(); // TODO 当前登录用户是否有权限，统一一个方法放到BaseService中

        // 参数校验数组
        $rules = [
            'page' => 'sometimes|integer',
            'limit' => 'sometimes|integer',
        ];
        $validate = Validator::make($request, $rules);
        if ($validate->fails()) {
            $error_list = $validate->errors()->all();
            $responseDto->status = ErrorMsg::PARAM_ERROR;
            $responseDto->msg = implode("\r\n", $error_list);
            return $responseDto;
        }

        // 获取数据，包含总数字段
        $list = $this->theRepository->getList($request);
        if (!$list || !isset($list[$responseDto::DTO_FIELD_TOTOAL]) || !isset($list[$responseDto::DTO_FIELD_LIST])) {
            ErrorMsg::FillResponseAndLog($responseDto, ErrorMsg::DATA_EMPTY);
            return $responseDto;
        }
        if ($list[$responseDto::DTO_FIELD_LIST]) {
            // 成功，返回列表信息
            foreach ($list[$responseDto::DTO_FIELD_LIST] as $key => $v_detail) {
                $v_info = new OrderCallRecordDto();
                $v_info->Assign($v_detail);
                $list[$responseDto::DTO_FIELD_LIST][$key] = $v_info;
            }
        }
        $data_list = new DataListDto();
        $data_list->Assign($list);
        $responseDto->data = $data_list;

        return $responseDto;
    }

    public function addOrUpdate($request = null) {
        if (!$request) $request = request()->all();
        $responseDto = new ResponseDto();

        if ('cli' != php_sapi_name()) $current_uid = auth('api')->id();
        else $current_uid = ($request['creator_id'] ?? 0) + 0;
        // 参数校验数组, 当前登录用户是否有权限暂不验证，后面统一处理
        //$field_id = 'id';
        $rules = [
            'id' => 'sometimes|integer',
        ];
        $validate = Validator::make($request, $rules);
        if ($validate->fails()) {
            $error_list = $validate->errors()->all();
            $responseDto->status = ErrorMsg::PARAM_ERROR;
            $responseDto->msg = implode("\r\n", $error_list);
            return $responseDto;
        }

        $curr_datetime = date('Y-m-d H:i:s');
        $data_arr = $request; // 全部作为
        if (isset($request['id']) && $request['id']) {
            // 修改的情况
            $data_arr['id'] = $request['id'];
            // 检查该记录是否存在
            $v_detail = $this->theRepository->getInfoById($request['id']);
            if (!$v_detail) {
                ErrorMsg::FillResponseAndLog($responseDto, ErrorMsg::DATA_NOT_EXISTS);
                return $responseDto;
            }
            $data_arr['updator_id'] = $current_uid;
            //$data_arr['deleted_time'] = $data_arr['deleted_time'] ?? $this->theRepository::DATETIME_NOT_NULL_DEFAULT;
        } else {
            // 新增，注：有些需要检查对应的唯一key是否存在
            //$v_detail = $this->theRepository->getByUniqueKey($request);
            //if ($v_detail) {
            //    ErrorMsg::FillResponseAndLog($responseDto, ErrorMsg::DATA_EXISTS);
            //    return $responseDto;
            //}
            $data_arr['creator_id'] = $current_uid;
            $data_arr['updator_id'] = $data_arr['creator_id'];
            $data_arr['created_time'] = $curr_datetime;
            //$data_arr['deleted_time'] = $this->theRepository::DATETIME_NOT_NULL_DEFAULT;
        }
        // 数据增加几个默认值
        $data_arr['updated_time'] = $curr_datetime;

        if (isset($request['id']) && $request['id']) {
            // 更新
            $rlt = $this->theRepository->updateData($request['id'], $data_arr);
            if (!$rlt) {
                ErrorMsg::FillResponseAndLog($responseDto, ErrorMsg::UPDATE_DB_FAILED);
                return $responseDto;
            }
        } else {
            $v_id = $this->theRepository->insertGetId($data_arr);
            if (!$v_id) {
                ErrorMsg::FillResponseAndLog($responseDto, ErrorMsg::INSERT_DB_FAILED);
                return $responseDto;
            }
            // 暂不返回详情，前端跳列表页
            $responseDto->data = ['id'=>$v_id];
        }
        return $responseDto;
    }

    public function detail($id) {
        $request['id'] = $id;
        $responseDto = new ResponseDto();

        // uid参数校验; 当前登录用户是否有权限暂不验证，后面统一处理
        $field_id = 'id';
        $rules = [
            $field_id => 'required|integer|min:1'
        ];
        $validate = Validator::make($request, $rules);
        if ($validate->fails()) {
            $error_list = $validate->errors()->all();
            $responseDto->status = ErrorMsg::PARAM_ERROR;
            $responseDto->msg = implode("\r\n", $error_list);
            return $responseDto;
        }

        $v_detail = $this->theRepository->getInfoById($request[$field_id]);
        if (!$v_detail) {
            ErrorMsg::FillResponseAndLog($responseDto, ErrorMsg::DATA_EMPTY);
            return $responseDto;
        }
        // 成功，返回信息
        $v_info = new OrderCallRecordDto();
        $v_info->Assign($v_detail);
        $responseDto->data = $v_info;

        return $responseDto;
    }

    public function delete($id) {
        // 进行软删除，更新状态即可
        $request['status'] = '-1';
        $request['id'] = $id;
        return self::addOrUpdate($request);
    }

    // 更新单条
    public function updateOne($id) {
        $request = request()->all();
        $request['id'] = $id;
        $responseDto = new ResponseDto();

        // 参数校验数组, 当前登录用户是否有权限暂不验证，后面统一处理
        $rules = [
            'id' => 'required|integer|min:1'
        ];
        $validate = Validator::make($request, $rules);
        if ($validate->fails()) {
            $error_list = $validate->errors()->all();
            $responseDto->status = ErrorMsg::PARAM_ERROR;
            $responseDto->msg = implode("\r\n", $error_list);
            return $responseDto;
        }
        return self::addOrUpdate($request);
    }

    // 拨打电话，调用第三方请求接口
    public function callSomeBody() {
        $request = request()->all();
        $responseDto = new ResponseDto();

        $curr_time = date('Y-m-d H:i:s');
        $rules = [
            'tel' => 'required',
            'order_no' => 'required'    // order_no
        ];
        $validate = Validator::make($request, $rules);
        if ($validate->fails()) {
            $error_list = $validate->errors()->all();
            $responseDto->status = ErrorMsg::PARAM_ERROR;
            $responseDto->msg = implode("\r\n", $error_list);
            return $responseDto;
        }

        $login_user_info = self::getCurrentLoginUserInfo();

        // 获取该用户配置的分机号
        $json_data = $this->getJsonDataByUser($login_user_info, $request);
        //print_r($json_data);exit;
        if (!is_array($json_data) || !isset($json_data[0])) {
            // 错误信息细分
            if ($json_data <= 3) {
                ErrorMsg::FillResponseAndLog($responseDto, ErrorMsg::ORDER_DATA_NOT_EXISTS);
                return $responseDto;
            }
            ErrorMsg::FillResponseAndLog($responseDto, ErrorMsg::SIPNO_COUNTRY_NOT_SET);
            return $responseDto;
        }

        // 调用接口
        $url = '/api_sipcall.cfm';
        // 调用第三方接口
        $client = new Client([
            'headers' => ['Content-Type' => 'application/json'],
            'base_uri' => env('call_url_3cx', 'http://178.128.123.197:8888'), // 根域名
            'timeout'  => 6.0,  // 超时
            'json'     => $json_data,
        ]);
        $res = $client->post($url);
        $l_rlt = $res->getBody()->getContents();

        if (200 != $res->getStatusCode() || !$l_rlt) {
            ErrorMsg::FillResponseAndLog($responseDto, ErrorMsg::UNKNOWN_ERROR);
            return $responseDto;
        }
        $l_return = json_decode($l_rlt, true);
        if (!$l_return || !isset($l_return['code'])) {
            ErrorMsg::FillResponseAndLog($responseDto, ErrorMsg::UNKNOWN_ERROR);
            return $responseDto;
        }

        if (200 != $l_return['code']) {
            // 失败
            ErrorMsg::FillResponseAndLog($responseDto, ErrorMsg::CALL_3CX_ERROR);
            if (isset($l_return['message'])) $responseDto->msg .= ' '. $l_return['message'];
            return $responseDto;
        }

        if (!$this->orderRepository) $this->orderRepository = new OrderRepository();
        try {
            // 更新订单的通话次数和通话时间、通话时长
            $update_arr = [
                'call_time' => $curr_time,
                'call_duration' => 0,       // 本次通话时长重置为0
            ];
            $this->orderRepository->updateData($request['order_no'], $update_arr);
            Order::where('order_no', $request['order_no'])->increment('call_num');  // 原子+1
        } catch(\Exception $e){
            \Log::error('order-call-error: ' . $e->getMessage());
        }
        return $responseDto;    // 成功
    }

    // 从数据库获取用户拨打电话的配置
    public function getJsonDataByUser($login_user_info, $request) {
        if (!isset($request['tel']) || !$request['tel'] || !isset($request['order_no']) || !$request['order_no']) {
            \Log::error('order-call-error, $request: ' . var_export($request, true));
            return 1;
        }
        // 1. 拼装电话，需要依据订单的国家拼装
        // 获取订单信息，找到订单的国家
        if (!$this->orderRepository) $this->orderRepository = new OrderRepository();
        // $request['order_no'] 兼容order_no
        //if (is_int($request['order_no'])) $order_detail = $this->orderRepository->getInfoById($request['order_no']);
        //else
            $order_detail = $this->orderRepository->getInfoByOrderNo($request['order_no']);
        if (!$order_detail || !isset($order_detail['country_id']) || !$order_detail['country_id']) {
            \Log::error('order-call-error, order_no: ' . $request['order_no'] . ', $order_detail: ' . var_export($order_detail, true));
            return 2;
        }
        if (!$this->countryRepository) $this->countryRepository = new CountryRepository();
        $country_detail = $this->countryRepository->getInfoById($order_detail['country_id']);
        if (!$country_detail || !isset($country_detail['phone_code'])) {
            \Log::error('order-call-error, country_id: ' . $order_detail['country_id'] . ', $country_detail: ' . var_export($country_detail, true));
            return 3;
        }
        $phone_code = str_replace(['+', '-'], '', $country_detail['phone_code']); // TODO 简单处理掉+ 复杂的以后优化
        $tel = $phone_code . $request['tel'];
        \Log::info('order-call-info, $phone_code: ' . $phone_code  . ', $tel: ' . $tel . ', $country_detail: ' . print_r($country_detail, true));

        // 2. 获取该用户拨打电话的设置，即sipno分机号
        $sql_where = [
            'user_id' => $login_user_info['id'],
            'country_id' => $order_detail['country_id'],
        ];
        $call_config = CallUserConfig::where($sql_where)->first();
        if (!$call_config) {
            \Log::error('order-call-error, $sql_where: ' . print_r($sql_where, true) . ', $call_config: ' . var_export($call_config, true));
            return 4;
        }
        $call_config = $call_config->toArray();
        $call_account = CallAccount::find($call_config['account_id']);
        if (!$call_account) {
            \Log::error('order-call-error, account_id: ' . $call_config['account_id'] . ', $call_account: ' . var_export($call_account, true));
            return 5;
        }
        $call_account = $call_account->toArray();
        if (!isset($call_account['account_info']) || !$call_account['account_info']) {
            \Log::error('order-call-error, $call_account: ' . var_export($call_account, true));
            return 6;
        }
        $json_data_db = json_decode($call_account['account_info'], true);
        if (!$json_data_db || !isset($json_data_db[0]) || !$json_data_db[0] || !isset($json_data_db[0]['sipno'])) {
            \Log::error('order-call-error, $json_data_db: ' . var_export($json_data_db, true));
            return 7;
        }

        // 拼装数据
        $new_data = [
            'sipno'     => $json_data_db[0]['sipno'],
            'phoneno'   => $tel,
            'orderid'   => $order_detail['order_no'] . '.' . $login_user_info['id'],  // orderid:订单id+"."+用户id @puqingyu
        ];
        $new_data = array_merge($json_data_db[0], $new_data);
        return [$new_data];
    }
}
