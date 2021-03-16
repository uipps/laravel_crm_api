<?php

namespace App\Services\Goods;

use App\Dto\DataListDto;
use App\Jobs\GoodsAddMultiple;
use App\Libs\Utils\ErrorMsg;
use App\Dto\ResponseDto;
use App\Dto\GoodsInfoDto;
use App\Models\Goods\GoodsPrice;
use App\Repositories\Goods\GoodsInfoRepository;
use App\Repositories\Admin\UserRepository;
use App\Repositories\Goods\GoodsInfoSkuRepository;
use App\Repositories\Goods\GoodsPriceRepository;
use App\Services\BaseService;
use Illuminate\Support\Facades\Validator;
use GuzzleHttp\Client;


class GoodsInfoService extends BaseService
{
    protected $theRepository;
    protected $goodsInfoSkuRepository;
    protected $goodsPriceRepository;

    public function __construct() {
        $this->theRepository = new GoodsInfoRepository();
        $this->goodsInfoSkuRepository = new GoodsInfoSkuRepository();
        $this->goodsPriceRepository = new GoodsPriceRepository();
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
            // 还需要返回sku
            // 获取用户id列表
            $gods_id_list = [];
            foreach ($list[$responseDto::DTO_FIELD_LIST] as $key => $user_detail) {
                $gods_id_list[] = $user_detail['id'];
            }
            $sku_list = $this->goodsInfoSkuRepository->getListByGoodsIds($gods_id_list);
            $price_list = $this->goodsPriceRepository->getPriceListByGoodsIds($gods_id_list);

            // 成功，返回列表信息
            foreach ($list[$responseDto::DTO_FIELD_LIST] as $key => $v_detail) {
                $v_info = new GoodsInfoDto();
                $v_info->Assign($v_detail);
                if (isset($sku_list[$v_detail['id']])) {
                    foreach ($sku_list[$v_detail['id']] as $sku_detail) {
                        $v_info->sku_list[] = self::formatSkuDetail($sku_detail);
                    }
                }
                if (isset($price_list[$v_detail['id']])) {
                    foreach ($price_list[$v_detail['id']] as $price_detail) {
                        $v_info->price_list[] = self::formatPriceDetail($price_detail);
                    }
                }
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

        $curr_datetime = date('Y-m-d H:i:s');
        if ('cli' != php_sapi_name()) $current_uid = auth('api')->id();
        else $current_uid = ($request['creator_id'] ?? 0) + 0;
        // 参数校验数组, 当前登录用户是否有权限暂不验证，后面统一处理
        //$field_id = 'id';
        $rules = [
            'id' => 'sometimes|integer',
        ];
        if (isset($request['erp_product_ids'])) {
            $rules['erp_product_ids'] = 'required'; // 批量添加
        } else {
            $rules['erp_product_id'] = 'required|integer';
            $rules['status'] = 'required|integer';
            $rules['product_name'] = 'required|string';
            //$rules['unit_price'] = 'required|numeric';
            $rules['pic_url'] = 'required|string';

            if (isset($request['id']) && $request['id'])
                $rules['sku_list'] = 'required';
            // 字段映射
            if (!isset($request['unit_price'])) $request['product_price'] = $request['unit_price'] = 0; // 改成了多个国家，每个国家一个价格
        }
        $attributes = [
            'unit_price' => __('field.unit_price')
        ];

        $validate = Validator::make($request, $rules, [], $attributes);
        if ($validate->fails()) {
            $error_list = $validate->errors()->all();
            $responseDto->status = ErrorMsg::PARAM_ERROR;
            $responseDto->msg = implode("\r\n", $error_list);
            return $responseDto;
        }

        // 批量添加商品，需要用队列处理
        if (isset($request['erp_product_ids'])) {
            // 因为多个可能超时，因此需要放到队列里面进行处理
            //   默认都是停用状态，因为价格、名称需要再次编辑的时候进行填写！图片采用erp系统默认产品图片
            $request['creator_id'] = $current_uid;
            $request['created_time'] = $curr_datetime;
            dispatch(new GoodsAddMultiple($request));
            if (count($request['erp_product_ids']) > 3)
                sleep(5);
            else sleep(3);
            return $responseDto;
        }

        $data_arr = $request; // 全部作为
        if (isset($request['id']) && $request['id']) {
            /*if (isset($data_arr['price_list']) && !$data_arr['price_list']) {
                ErrorMsg::FillResponseAndLog($responseDto, ErrorMsg::GOODS_PRICE_MUST_BIGGER_ZERO);
                return $responseDto;
            }*/
            // 修改的情况
            $data_arr['id'] = $request['id'];
            // 检查该记录是否存在
            $v_detail = $this->theRepository->getInfoById($request['id']);
            if (!$v_detail) {
                ErrorMsg::FillResponseAndLog($responseDto, ErrorMsg::DATA_NOT_EXISTS);
                return $responseDto;
            }
            // erp_product_id 不允许修改
            if ($v_detail['erp_product_id'] != $request['erp_product_id']) {
                ErrorMsg::FillResponseAndLog($responseDto, ErrorMsg::PARAM_ERROR);
                return $responseDto;
            }

            $data_arr['updator_id'] = $current_uid;
            //$data_arr['deleted_time'] = $data_arr['deleted_time'] ?? $this->theRepository::DATETIME_NOT_NULL_DEFAULT;
        } else {
            if (!isset($data_arr['price_list']) || !$data_arr['price_list']) {
                ErrorMsg::FillResponseAndLog($responseDto, ErrorMsg::GOODS_PRICE_MUST_BIGGER_ZERO);
                return $responseDto;
            }
            // 新增，默认值
            $data_arr['creator_id'] = $current_uid;
            $data_arr['updator_id'] = $data_arr['creator_id'];
            $data_arr['created_time'] = $curr_datetime;
            //$data_arr['deleted_time'] = $this->theRepository::DATETIME_NOT_NULL_DEFAULT;
        }
        // 数据增加几个默认值
        $data_arr['updated_time'] = $curr_datetime;

        if (isset($request['id']) && $request['id']) {
            // 更新
            $rlt = $this->updateGoodsAndSkuData($request['id'], $data_arr, $curr_datetime, $current_uid);
            if (!$rlt) {
                ErrorMsg::FillResponseAndLog($responseDto, ErrorMsg::UPDATE_DB_FAILED);
                return $responseDto;
            }
        } else {
            // 如果已经添加过了，则提示前端，该商品已存在
            $exist_db = $this->theRepository->getInfoByErpProductId($request['erp_product_id']);
            if ($exist_db) {
                ErrorMsg::FillResponseAndLog($responseDto, ErrorMsg::GOODS_EXISTS);
                return $responseDto;
            }

            $erp_goods_info = $this->erpProductDetail($request['erp_product_id']);
            $erp_goods_info = $erp_goods_info->data;
            if (!$erp_goods_info) {
                ErrorMsg::FillResponseAndLog($responseDto, ErrorMsg::DATA_NOT_EXISTS);
                return $responseDto;
            }
            $erp_goods_info = array_merge($erp_goods_info, $request);
            $v_id = $this->insertGoodsAndSkuData($erp_goods_info, $request, $curr_datetime, $current_uid);
            if (!$v_id) {
                ErrorMsg::FillResponseAndLog($responseDto, ErrorMsg::INSERT_DB_FAILED);
                return $responseDto;
            }
            // 暂不返回详情，前端跳列表页
            $responseDto->data = ['id'=>$v_id];
        }
        return $responseDto;
    }

    // 商品详情
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
        // 还需要返回sku
        $sku_list = $this->goodsInfoSkuRepository->getListByGoodsId($id);
        $price_list = $this->goodsPriceRepository->getPriceListByGoodsIds([$id]);

        // 成功，返回信息
        $v_info = new GoodsInfoDto();
        $v_info->Assign($v_detail);
        if ($sku_list) {
            foreach ($sku_list as $sku_detail) {
                $v_info->sku_list[] = self::formatSkuDetail($sku_detail);
            }
        }
        if ($price_list && isset($price_list[$id])) {
            foreach ($price_list[$id] as $price_detail) {
                $v_info->price_list[] = self::formatPriceDetail($price_detail);
            }
        }
        $responseDto->data = $v_info;

        return $responseDto;
    }

    public function delete($id) {
        $responseDto = new ResponseDto();
        // 进行软删除，更新状态即可
        $request['status'] = '-1';
        $request['id'] = $id;

        \DB::beginTransaction();
        try {
            GoodsPrice::where(['goods_id' => $id])->delete(); // 删除的情况，顺便删除价格
            $rlt = $this->theRepository->updateData($request['id'], $request);
            if (!$rlt) {
                ErrorMsg::FillResponseAndLog($responseDto, ErrorMsg::UPDATE_DB_FAILED);
                return $responseDto;
            }
            \DB::commit();
        } catch (\Exception $e) {
            $msg = 'db-Transaction-error: delete, ' . $e->getMessage() . ' data:';
            \Log::error($msg, $request);
            \DB::rollBack();
            ErrorMsg::FillResponseAndLog($responseDto, ErrorMsg::UPDATE_DB_FAILED);
            return $responseDto;
        }
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

    // 通过 erp_product_id 抓取该商品信息，以及该商品对应的所有的sku信息
    public function erpProductDetail($erp_product_id) {
        $request = request()->all();
        $request['erp_product_id'] = $erp_product_id;
        $responseDto = new ResponseDto();

        $rules = [
            'erp_product_id' => 'required|integer|min:1'
        ];
        $validate = Validator::make($request, $rules);
        if ($validate->fails()) {
            $error_list = $validate->errors()->all();
            $responseDto->status = ErrorMsg::PARAM_ERROR;
            $responseDto->msg = implode("\r\n", $error_list);
            return $responseDto;
        }
        $curr_datetime = date('Y-m-d H:i:s');
        $login_user_info = self::getCurrentLoginUserInfo();

        // 1. 调用产品接口
        $url = '/index.php?g=product&m=api&a=get&id=' . $erp_product_id;
        $client = new Client([
            'base_uri' => env('erp_api_url'), // 根域名
            'timeout'  => 6,  // 超时
        ]);
        $res = $client->get($url);
        $l_rlt = $res->getBody()->getContents();
        if (200 != $res->getStatusCode() || !$l_rlt) {
            ErrorMsg::FillResponseAndLog($responseDto, ErrorMsg::NETWORK_ERROR);
            return $responseDto;
        }
        $l_rlt = json_decode($l_rlt, true);
        if (!$l_rlt || !isset($l_rlt['product']) || !isset($l_rlt['product']['inner_name']) || !$l_rlt['product']['inner_name']) {
            ErrorMsg::FillResponseAndLog($responseDto, ErrorMsg::ERP_API_DATA_CHANGE);
            return $responseDto;
        }
        $erp_goods_info = self::getGoodsInfoByErpProductInfo($l_rlt['product']);

        // 2. 调用sku接口，通过产品内部名获取该产品所有的sku
        $url = '/index.php?g=product&m=api&a=get_sku_with_name&inner_name=' . urlencode(trim($l_rlt['product']['inner_name']));
        $res = $client->get($url);
        $l_rlt = $res->getBody()->getContents();
        if (200 != $res->getStatusCode() || !$l_rlt) {
            ErrorMsg::FillResponseAndLog($responseDto, ErrorMsg::NETWORK_ERROR);
            return $responseDto;
        }
        $l_rlt = json_decode($l_rlt, true);
        if (!$l_rlt || !isset($l_rlt['data']) || !$l_rlt['data']) {
            ErrorMsg::FillResponseAndLog($responseDto, ErrorMsg::ERP_API_DATA_CHANGE);
            return $responseDto;
        }
        // 获取到了产品的sku列表 $sku_detail['option_values']
        $erp_goods_info['sku_list'] = $l_rlt['data'];

        $responseDto->data = $erp_goods_info;
        return $responseDto;
    }

    public function getGoodsInfoByErpProductInfo($erp_product_info) {
        // 只需要部分字段，erp接口数据的映射关系：
        $erp_goods_info = [
            'erp_product_id' => $erp_product_info['id'],
            'product_name' => $erp_product_info['title'],
            'foreign_name' => $erp_product_info['foreign_title'],
            'internal_name' => $erp_product_info['inner_name'],
            'pic_url' => '',
            'spu' => $erp_product_info['model'],
            'sell_price' => $erp_product_info['price'],
            'unit_price' => 0,           // 对外字段
            'product_price' => 0,           // 数据表字段
        ];
        if (isset($erp_product_info['thumbs']) && $erp_product_info['thumbs']) {
            $thumbs = json_decode($erp_product_info['thumbs'], true);
            if (isset($thumbs['photo']) && $thumbs['photo'] && isset($thumbs['photo'][0]) && isset($thumbs['photo'][0]['url'])) {
                $pic_url = $thumbs['photo'][0]['url'];
                if (false === strpos($pic_url, '://')) $pic_url = rtrim(env('erp_api_url'), '/') . '/data/upload/' . ltrim($pic_url, '/');
                $erp_goods_info['pic_url'] = $pic_url;
            }
        }

        return $erp_goods_info;
    }

    // 插入产品信息和多个sku，需要进行事务处理
    public function insertGoodsAndSkuData($erp_goods_info, $request, $create_time='', $creator_id=0) {
        if (isset($request['unit_price'])) $erp_goods_info['product_price'] = $request['unit_price'];

        \DB::beginTransaction();
        try {
            $goods_id = $this->theRepository->insertGetId($erp_goods_info);
            if (!$goods_id)
                return false;
            $this->goodsInfoSkuRepository->insertSkuMultiByGoodsId($goods_id, $erp_goods_info, $create_time, $creator_id);
            $this->goodsPriceRepository->insertPriceMultiByGoodsId($goods_id, $erp_goods_info, $create_time, $creator_id);
            \DB::commit();
        } catch (\Exception $e) {
            $msg = 'db-Transaction-error: insert, ' . $e->getMessage() . ' data:';
            \Log::error($msg, $request);
            \DB::rollBack();
            return false;
        }
        return $goods_id;
    }

    public function updateGoodsAndSkuData($goods_id, $request, $create_time='', $creator_id=0) {
        \DB::beginTransaction();
        try {
            $this->goodsInfoSkuRepository->updateSkuMultiByGoodsId($goods_id, $request, $create_time, $creator_id);
            $this->goodsPriceRepository->updatePriceMultiByGoodsId($goods_id, $request, $create_time, $creator_id);
            // 有几项不能修改
            if (isset($request['spu'])) unset($request['spu']);
            if (isset($request['sell_price'])) unset($request['sell_price']);
            if (isset($request['internal_name'])) unset($request['internal_name']);
            if (isset($request['erp_product_id'])) unset($request['erp_product_id']);
            $this->theRepository->updateData($goods_id, $request);
            \DB::commit();
        } catch (\Exception $e) {
            $msg = 'db-Transaction-error: insert, ' . $e->getMessage() . ' data:';
            \Log::error($msg, $request);
            \DB::rollBack();
            return false;
        }
        return $goods_id;
    }

    // 检查 erp_product_id 是否已经添加过了
    public function getInfoByErpProductId($erp_product_id) {
        return $this->theRepository->getInfoByErpProductId($erp_product_id);
    }

    public function formatSkuDetail($sku_detail) {
        if ($sku_detail['option_values']) {
            $sku_detail['option_values'] = json_decode($sku_detail['option_values'], true); // 返回对象
            unset($sku_detail['sku']);
            $sku_detail = array_merge($sku_detail['option_values'], $sku_detail);   // 保持跟拉取的erp商品数据字段不见少，便于前端, 状态是本系统的状态
            unset($sku_detail['option_values']);
        } else {
            $sku_detail['model'] = '';          // 补充几个前端使用的字段
            $sku_detail['barcode'] = '';
            $sku_detail['title'] = '';
        }

        // 前端要求 status使用字符串类型，跟erp拉取的sku中status数据类型一致
        if (isset($sku_detail['status']) && is_int($sku_detail['status'])) {
            $sku_detail['status'] = (string)$sku_detail['status'];
        }

        return $sku_detail;
    }

    public function formatPriceDetail($price_detail) {
        if (isset($price_detail['deletor_id'])) unset($price_detail['deletor_id']);
        if (isset($price_detail['deleted_time'])) unset($price_detail['deleted_time']);
        return $price_detail;
    }

}
