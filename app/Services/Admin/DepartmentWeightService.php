<?php

namespace App\Services\Admin;

use App\Libs\Utils\ErrorMsg;
use App\Dto\DataListDto;
use App\Dto\ResponseDto;
use App\Dto\DepartmentWeightDto;
use App\Repositories\Admin\DepartmentWeightRepository;
use App\Repositories\Admin\UserRepository;
use App\Services\BaseService;
use Illuminate\Support\Facades\Validator;


class DepartmentWeightService extends BaseService
{
    protected $theRepository;

    public function __construct() {
        $this->theRepository = new DepartmentWeightRepository();
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
                $v_info = new DepartmentWeightDto();
                $v_info->Assign($this->addAttrName2Data($v_detail,[],['country_id','department_id','status']));
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

    public function detail($id){
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
        $v_info = new DepartmentWeightDto();
        $v_info->Assign($this->addAttrName2Data($v_detail,[],['country_id','department_id','status']));
        $responseDto->data = $v_info;

        return $responseDto;
    }

    public function delete($id) {
        // 进行软删除，更新状态即可
        $request['status'] = '-1';
        $request['id'] = $id;
        return self::addOrUpdate($request);
    }

    // 设置部门分单比例, 多个部门放在一起，一起提交分单比例，必须保证合计100，需要用到事务
    public function setDeptOrderRate() {

        $request = request()->all();
        $responseDto = new ResponseDto();

        // uid参数校验; 当前登录用户是否有权限暂不验证，后面统一处理
        $rules = [
            'id' => 'required',
            'rate' => 'required'
        ];
        $validate = Validator::make($request, $rules);
        if ($validate->fails()) {
            $error_list = $validate->errors()->all();
            $responseDto->status = ErrorMsg::PARAM_ERROR;
            $responseDto->msg = implode("\r\n", $error_list);
            return $responseDto;
        }

        // 多条数据采用下标方式，可能是多维数组 id[], rate[].
        if (!is_array($request['id']) || !is_array($request['rate'])) {
            ErrorMsg::FillResponseAndLog($responseDto, ErrorMsg::PARAM_ERROR); // 应该是数组类型
            return $responseDto;
        }
        if (count($request['id']) != count($request['rate'])) {
            ErrorMsg::FillResponseAndLog($responseDto, ErrorMsg::PARAM_ERROR); // 数量不匹配
            return $responseDto;
        }
        $data_arr = [];
        $rate_total = 0;
        foreach ($request['id'] as $key => $id_value) {
            // key必须保证对应
            if (!isset($request['rate'][$key])) {
                ErrorMsg::FillResponseAndLog($responseDto, ErrorMsg::PARAM_ERROR); // 数量不匹配
                return $responseDto;
            }
            $rate_total += $request['rate'][$key];
            $row = [
                'id' => $id_value,
                'percent' => $request['rate'][$key], // 数据库中的字段是percent
            ];
            $data_arr[] = $row;
        }
        /*// 检查是否100
        if (100 != $rate_total) {
            ErrorMsg::FillResponseAndLog($responseDto, ErrorMsg::PARAM_ERROR);
            return $responseDto;
        }*/
        $v_rlt = $this->theRepository->setDeptOrderRate($data_arr);
        if (!$v_rlt) {
            ErrorMsg::FillResponseAndLog($responseDto, ErrorMsg::UPDATE_DB_FAILED);
            return $responseDto;
        }
        return $responseDto;
    }

    // 通过获取某个部门（可无）某指定国家（可多个）的权重、分单比例,一定要同级别
    public function getDeptOrderRateByCountry() {
        $request = request()->all();
        $responseDto = new ResponseDto();

        $rules = [
            'department_id' => 'sometimes|integer',
            'country_ids' => 'required', // 字符串，多条用逗号分割，前端暂时没有传多个的需求
            'parent_id' => 'required'
        ];
        $validate = Validator::make($request, $rules);
        if ($validate->fails()) {
            $error_list = $validate->errors()->all();
            $responseDto->status = ErrorMsg::PARAM_ERROR;
            $responseDto->msg = implode("\r\n", $error_list);
            return $responseDto;
        }

        // 获取数据，包含总数字段
        $list = $this->theRepository->getDeptOrderRateByCountry($request);
        if (!$list || !isset($list[$responseDto::DTO_FIELD_TOTOAL]) || !isset($list[$responseDto::DTO_FIELD_LIST])) {
            ErrorMsg::FillResponseAndLog($responseDto, ErrorMsg::DATA_EMPTY);
            return $responseDto;
        }
        if ($list[$responseDto::DTO_FIELD_LIST]) {
            // 成功，返回列表信息
            foreach ($list[$responseDto::DTO_FIELD_LIST] as $key => $v_detail) {
                $v_info = new DepartmentWeightDto();
                $v_info->Assign($this->addAttrName2Data($v_detail,[],['country_id','department_id','status']));
                $list[$responseDto::DTO_FIELD_LIST][$key] = $v_info;
            }
        }
        $data_list = new DataListDto();
        $data_list->Assign($list);
        $responseDto->data = $data_list;

        return $responseDto;
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
}
