<?php

namespace App\Services\Customer;

use App\Libs\Utils\ErrorMsg;
use App\Dto\DataListDto;
use App\Dto\ResponseDto;
use App\Dto\CustomerLabelDto;
use App\Models\Customer\CustomerLabel;
use App\Repositories\Customer\CustomerLabelRepository;
use App\Repositories\Admin\UserRepository;
use App\Repositories\Customer\CustomerRepository;
use App\Services\BaseService;
use Illuminate\Support\Facades\Validator;


class CustomerLabelService extends BaseService
{
    protected $theRepository;
    protected $customerRepository;

    public function __construct() {
        $this->theRepository = new CustomerLabelRepository();
        $this->userRepository = new UserRepository(); // 用于权限检查
        $this->customerRepository = new CustomerRepository();
    }

    public function getList() {
        $request = request()->all(); // 参数接收
        $responseDto = new ResponseDto();

        //$login_user_info = self::getCurrentLoginUserInfo(); // TODO 当前登录用户是否有权限，统一一个方法放到BaseService中

        // 参数校验数组
        $rules = [
            'customer_id' => 'required|integer|min:1', // 客户的备注，必须指定客户id
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
                $v_info = new CustomerLabelDto();
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
            'customer_id' => 'required|integer|min:1',
            'label_value' => 'required|string|min:1',
        ];
        $validate = Validator::make($request, $rules);
        if ($validate->fails()) {
            $error_list = $validate->errors()->all();
            $responseDto->status = ErrorMsg::PARAM_ERROR;
            $responseDto->msg = implode("\r\n", $error_list);
            return $responseDto;
        }
        // 只有客户质量可编辑
        if (isset($request['label_type']) && CustomerLabel::LABEL_TYPE_QUALITY != $request['label_type']) {
            ErrorMsg::FillResponseAndLog($responseDto, ErrorMsg::PARAM_ERROR);
            return $responseDto;
        }
        $request['label_type'] = CustomerLabel::LABEL_TYPE_QUALITY; // 强制为5
        $request['label_style'] = CustomerLabel::LABEL_STYLE_QUALITY; // 多值标签，此字段暂时无用，先带上值

        // 检查用户质量标签是否存在，目前规定最多有且仅有一条
        $v_detail = $this->theRepository->getInfoByCustomerIdType($request['customer_id'], $request['label_type']);

        // 检查数据是否存在，这个唯一性不是id，而是 customer_id 和 label_type
        $curr_datetime = date('Y-m-d H:i:s');
        $data_arr = $request; // 全部作为
        if ($v_detail) {
            // 修改的情况
            $request['id'] = $v_detail['id'];

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
        try {
            // 维护一下customer表的quality_level字段
            $this->customerRepository->updateData($request['customer_id'], ['quality_level'=>$request['label_value']]);
            //Customer::find($request['customer_id'])->update(['quality_level'=>$request['label_value']]);
        } catch(\Exception $e){
            \Log::error('customer-update-quality_level-error: ' . $e->getMessage());
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
        $v_info = new CustomerLabelDto();
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
        $request['customer_id'] = $id;
        $responseDto = new ResponseDto();

        // 参数校验数组, 当前登录用户是否有权限暂不验证，后面统一处理
        $rules = [
            'label_value' => 'required|string|min:1',
            'customer_id' => 'required|integer|min:1',
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
}
