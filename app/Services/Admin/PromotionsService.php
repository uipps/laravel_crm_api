<?php

namespace App\Services\Admin;

use App\Dto\GoodsInfoSkuDto;
use App\Dto\PromotionsGoodsNumRuleDto;
use App\Libs\Utils\ErrorMsg;
use App\Dto\DataListDto;
use App\Dto\ResponseDto;
use App\Dto\PromotionsDto;
use App\Models\Admin\Promotions;
use App\Models\Admin\PromotionsHistory;
use App\Repositories\Admin\PromotionsGoodsNumRuleRepository;
use App\Repositories\Admin\PromotionsGoodsRepository;
use App\Repositories\Admin\PromotionsRepository;
use App\Repositories\Admin\UserRepository;
use App\Services\BaseService;
use Illuminate\Support\Facades\Validator;


class PromotionsService extends BaseService
{
    protected $theRepository;
    protected $promotionRulesRepository;
    protected $promotionGoodsRepository;

    public function __construct() {
        $this->theRepository = new PromotionsRepository();
        $this->promotionRulesRepository = new PromotionsGoodsNumRuleRepository();
        $this->promotionGoodsRepository = new PromotionsGoodsRepository();
        $this->userRepository = new UserRepository(); // 用于权限检查
    }

    public function getList($request=null) {
        if (!$request) $request = request()->all(); // 参数接收
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
            $list[$responseDto::DTO_FIELD_LIST] = self::getPromotionsWithRules($list[$responseDto::DTO_FIELD_LIST]);
            // 成功，返回列表信息
            foreach ($list[$responseDto::DTO_FIELD_LIST] as $key => $v_detail) {
                $v_info = new PromotionsDto();
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
        $rules = [
            'id' => 'sometimes|integer',
            'status' => 'sometimes|integer',
        ];
        if (!isset($request['id'])) {
            $rules['name']        = 'required|string';        // 活动名称
            $rules['rule_attr']   = 'required|integer|min:1'; // 1-可叠加 2-不可叠加
            $rules['rule_scope']  = 'required|integer|min:1';
            $rules['goods_scope'] = 'required|integer|min:1';
            $rules['status']      = 'required|integer';
            $rules['promotion_rules'] = 'required';           // 必须有规则数据

            if (isset($request['goods_scope']) && 2 == $request['goods_scope']) // 单个商品，必须提供商品
                $rules['promotion_goods'] = 'required';       // 商品信息
        }

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
            $data_arr['updator_id'] = $current_uid;
            if (isset($request['status']) && -1 == $request['status'])
                $data_arr['deleted_time'] = $curr_datetime;
        } else {
            $data_arr['creator_id'] = $current_uid;
            $data_arr['updator_id'] = $data_arr['creator_id'];
            $data_arr['created_time'] = $curr_datetime;
            $data_arr['deleted_time'] = $this->theRepository::DATETIME_NOT_NULL_DEFAULT;
        }
        // 数据增加几个默认值
        $data_arr['updated_time'] = $curr_datetime;

        if (isset($request['id']) && $request['id']) {
            // 修改的情况
            $data_arr['id'] = $request['id'];
            // 检查该记录是否存在
            $v_detail = $this->theRepository->getInfoById($request['id']);
            if (!$v_detail) {
                ErrorMsg::FillResponseAndLog($responseDto, ErrorMsg::DATA_NOT_EXISTS);
                return $responseDto;
            }

            // 更新
            $rlt = $this->updatePromotionAndRuleAndGoods($data_arr, $request, $curr_datetime, $current_uid);
            if (!$rlt) {
                ErrorMsg::FillResponseAndLog($responseDto, ErrorMsg::UPDATE_DB_FAILED);
                return $responseDto;
            }
        } else {
            $v_id = $this->insertPromotionAndRuleAndGoods($data_arr, $request, $curr_datetime, $current_uid);
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

        $list = [$v_detail];
        $list = self::getPromotionsWithRules($list);
        $v_detail = $list[0];

        // 成功，返回信息
        $v_info = new PromotionsDto();
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

    // 活动需要返回活动规则
    public function getPromotionsWithRules($orig_user_list) {
        // 也可放到逻辑层，因为需要调用不同的service；// TODO 需要优化，因为数据量可能大
        $l_weight_all = $this->promotionRulesRepository->getAllPromotionsRules();  // 数据量大的话，只获取指定活动id的规则列表
        $l_goods_all = $this->promotionGoodsRepository->getAllPromotionsGoodsSku();  // 数据量大的话，只获取指定活动id的商品
        if ($l_weight_all) {
            // 按照用户id归类
            $l_weight_list = [];
            foreach ($l_weight_all as $row) {
                $l_weight_list[$row['promotion_id']][] = $row;
            }
        }

        foreach ($orig_user_list as &$row) {
            if (!isset($l_weight_list[$row['id']]))
                continue;

            foreach ($l_weight_list[$row['id']] as $v_detail) {
                $v_info = new PromotionsGoodsNumRuleDto();
                $v_info->Assign($this->addPromotionRuleName($v_detail));
                $row['promotion_rules'][] = $v_info; // 加一个字段
            }

            // 如果活动的范围是单个商品
            if (2 == $row['goods_scope'] && isset($l_goods_all[$row['id']]) && $l_goods_all[$row['id']]) {
                foreach ($l_goods_all[$row['id']] as $v_detail) {
                    $v_info = new GoodsInfoSkuDto();
                    $v_info->Assign($v_detail);
                    $row['promotion_goods'][] = $v_info; //
                    $row['promotion_goods_sku'][] = $v_detail['sku']; // 单独为@jingbo 添加的字段
                }
            }
        }
        return $orig_user_list;
    }

    // 插入活动规则、活动商品（如果有），活动规则、活动商品放在另一张表，需要进行事务处理
    public function insertPromotionAndRuleAndGoods($data_arr, $request, $create_time='', $creator_id=0) {
        \DB::beginTransaction();
        try {
            $promotion_id = $this->theRepository->insertGetId($data_arr);
            if (!$promotion_id)
                return false;
            $this->promotionRulesRepository->insertRuleMultiByPromotionId($promotion_id, $data_arr, $create_time, $creator_id);
            if (isset($data_arr['goods_scope']) && 2 == $data_arr['goods_scope'] && $data_arr['promotion_goods'])
                $this->promotionGoodsRepository->insertGoodsMultiByPromotionId($promotion_id, $data_arr, $create_time, $creator_id);

            \DB::commit();

            $promotion = Promotions::with('promotion_rules')->find($promotion_id);
            PromotionsHistory::create([
                'promotions_id' => $promotion_id,
                'promotions_detail' => $promotion->toJson(JSON_UNESCAPED_UNICODE),
            ]);

        } catch (\Exception $e) {
            $msg = 'db-Transaction-error: insert, ' . $e->getMessage() . ' data:';
            \Log::error($msg, $request);
            \DB::rollBack();
            return false;
        }
        return $promotion_id;
    }

    // 更新活动规则、活动商品（如果有），活动规则、活动商品放在另一张表，需要进行事务处理
    public function updatePromotionAndRuleAndGoods($data_arr, $request, $create_time='', $creator_id=0) {
        \DB::beginTransaction();
        try{
            if (isset($data_arr['goods_scope']) && 2 == $data_arr['goods_scope'] && $data_arr['promotion_goods']) {
                $this->promotionGoodsRepository->updateGoodsMultiByPromotionId($request['id'], $data_arr, $create_time, $creator_id);
            }
            $this->promotionRulesRepository->updateRuleMultiByPromotionId($request['id'], $data_arr, $create_time, $creator_id);
            $this->theRepository->updateData($request['id'], $data_arr);
            \DB::commit();

            $promotion = Promotions::with('promotion_rules')->find($request['id']);
            PromotionsHistory::create([
                'promotions_id' => $request['id'],
                'promotions_detail' => $promotion->toJson(JSON_UNESCAPED_UNICODE),
            ]);

        } catch (\Exception $e) {
            $msg = 'db-Transaction-error: update, ' . $e->getMessage() . ' data:';
            \Log::error($msg, $request);
            \DB::rollBack();
            return false;
        }
        return true;
    }

    public function getListActive() {
        $request = request()->all();
        $request['status'] = 1;
        return $this->getList($request);
    }
}
