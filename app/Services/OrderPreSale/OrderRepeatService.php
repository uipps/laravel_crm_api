<?php

namespace App\Services\OrderPreSale;

use App\Dto\OrderStatsPreSaleDto;
use App\Libs\Utils\ErrorMsg;
use App\Dto\DataListDto;
use App\Dto\ResponseDto;
use App\Dto\OrderRepeatDto;
use App\Models\Admin\User;
use App\Repositories\OrderPreSale\OrderRepeatRepository;
use App\Repositories\Admin\UserRepository;
use App\Services\BaseService;
use Illuminate\Support\Facades\Validator;
use App\Repositories\OrderPreSale\OrderRepository;
use App\Repositories\OrderPreSale\OrderDetailRepository;
use App\Dto\OrderDetailDto;
use App\Dto\OrderDto;


class OrderRepeatService extends BaseService
{
    protected $theRepository;
    protected $departmentRepository;
    protected $orderRepository;
    protected $orderDetailRepository; // 就是商品信息

    public function __construct() {
        $this->theRepository = new OrderRepeatRepository();
        $this->orderRepository = new OrderRepository();
        $this->orderDetailRepository = new OrderDetailRepository();
        $this->userRepository = new UserRepository(); // 用于权限检查
    }

    public function getList($request=null) {
        if (!$request) $request = request()->all(); // 参数接收
        $responseDto = new ResponseDto();

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

        // 主管能看到本部门的全部列表；员工只能看到自己的
        $login_user_info = self::getCurrentLoginUserInfo();
        // 如果是管理员，能看到所有
        $child_dept_list = [];
        if (1 != $login_user_info['role_id']) {
            if (User::LEVEL_ADMIN == $login_user_info['level']) {
                // 主管能看到本部门的全部列表
                $child_dept_list = parent::getChildrenDepartmentByDeptId($login_user_info['department_id']);
                if (!isset($request['department_id'])) $request['department_id'] = ['in', $child_dept_list];
            } else if (User::LEVEL_STAFF == $login_user_info['level']) {
                // 员工只能看到自己的
                if (!isset($request['pre_sale_id'])) $request['pre_sale_id'] = $login_user_info['id'];
            }
        }
        $request['job_type'] = 1;   // 售前

        // 获取数据，包含总数字段
        $list = $this->theRepository->getList($request);
        if (!$list || !isset($list[$responseDto::DTO_FIELD_TOTOAL]) || !isset($list[$responseDto::DTO_FIELD_LIST])) {
            ErrorMsg::FillResponseAndLog($responseDto, ErrorMsg::DATA_EMPTY);
            return $responseDto;
        }
        if ($list[$responseDto::DTO_FIELD_LIST]) {
            // 所有的 order_id 列表
            $order_ids = [];
            foreach ($list[$responseDto::DTO_FIELD_LIST] as $v_detail) {
                $order_ids[] = $v_detail['order_id'];
            }
            // 通过 order_id，获取对应的订单详情数据
            $order_info_list = $this->orderRepository->getOrderInfosByOrderIds($order_ids); // 批量获取
            // 通过 order_id，获取订单对应的商品详情数据
            $goods_info_list = $this->orderDetailRepository->getGoodsListByOrderIds($order_ids); // 三维数组

            // 成功，返回列表信息
            foreach ($list[$responseDto::DTO_FIELD_LIST] as $key => $v_detail) {
                $list[$responseDto::DTO_FIELD_LIST][$key] = self::getOneOrderInfo($v_detail, $order_info_list, $goods_info_list);
            }
        }
        $data_list = new DataListDto();
        if (!isset($request[$responseDto::WITHOUT_ORDER_STATS]) || !$request[$responseDto::WITHOUT_ORDER_STATS]) {
            // 默认情况，所有订单列表都要带上订单统计数据；如果设置不需要携带统计数据则跳过
            $redis_stats_data = $this->orderRepository->getOrderNumStatsByUserId($login_user_info['id'], $login_user_info, $child_dept_list);
            $order_stat = new OrderStatsPreSaleDto();
            $order_stat->Assign($redis_stats_data); // 从redis缓存获取数据
            $data_list->meta[$responseDto::DTO_FIELD_ORDER_STATS] = $order_stat;
        }
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
            'order_id' => 'sometimes|integer',
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
        if (isset($request['order_id']) && $request['order_id']) {
            // 修改的情况
            $data_arr['order_id'] = $request['order_id'];
            // 检查该记录是否存在
            $v_detail = $this->theRepository->getInfoById($request['order_id']);
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

        if (isset($request['order_id']) && $request['order_id']) {
            // 更新
            $rlt = $this->theRepository->updateData($request['order_id'], $data_arr);
            if (!$rlt) {
                ErrorMsg::FillResponseAndLog($responseDto, ErrorMsg::UPDATE_DB_FAILED);
                return $responseDto;
            }
        } else {
            $v_id = 0; // $this->theRepository->insertGetId($data_arr); // 由数据服务插入，这里没有应用场景，先注释
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
        $request['order_id'] = $id;
        $responseDto = new ResponseDto();

        // uid参数校验; 当前登录用户是否有权限暂不验证，后面统一处理
        $field_id = 'order_id';
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
        // 通过 order_id，获取对应的订单详情数据
        $order_info_list = $this->orderRepository->getOrderInfosByOrderIds([$id]); // 批量获取
        // 通过 order_id，获取订单对应的商品详情数据
        $goods_info_list = $this->orderDetailRepository->getGoodsListByOrderIds([$id]); // 三维数组

        // 成功，返回信息
        $v_info = self::getOneOrderInfo($v_detail, $order_info_list, $goods_info_list);
        $responseDto->data = $v_info;

        return $responseDto;
    }

    public function delete($id) {
        // 进行软删除，更新状态即可
        $request['status'] = '-1';
        $request['order_id'] = $id;
        return self::addOrUpdate($request);
    }

    // 更新单条
    public function updateOne($id) {
        $request = request()->all();
        $request['order_id'] = $id;
        $responseDto = new ResponseDto();

        // 参数校验数组, 当前登录用户是否有权限暂不验证，后面统一处理
        $rules = [
            'order_id' => 'required|integer|min:1'
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

    private function getOneOrderInfo($v_detail, $order_info_list, $goods_info_list) {
        $order_id = $v_detail['order_id'];
        // 字段映射，数据表中只有status字段，无status_repeat字段，但是前端需要status_repeat字段；DTO修改没有作用，因为进行数组合并
        if (isset($v_detail['status']))
            $v_detail['status_repeat'] = $v_detail['status'];

        // DTO合并方法一，验证可行
        $v_info = array_merge((array)(new OrderRepeatDto()), (array)(new OrderDto()));
        if (isset($v_info['order_sale_id'])) {
            // 售前客服字段订单表中有
            unset($v_info['order_sale_id']);
            // $v_info['job_type'] = 1; // 售前
        }
        $v_info = \array_assign($v_info, $v_detail); // 赋值

        // 订单详情赋值
        if (isset($order_info_list[$order_id]))
            $v_info = \array_assign($v_info, $this->addAllAttrName2Data($order_info_list[$order_id]));

        // 商品详情赋值
        if (isset($goods_info_list[$order_id])) {
            foreach ($goods_info_list[$order_id] as $goods_val) {
                $goods_dto = new OrderDetailDto();
                $goods_dto->Assign($goods_val);
                $v_info['goods_info'][] = $goods_dto; // 可能有多条商品信息，放到字段 goods_info 上
            }
        }
        return $v_info;
    }

    // 获取某订单的重复列表：手机号相同、sku-id相同
    public function getRepeatListById($id) {
        //$request = request()->all();
        $request['order_id'] = $id;
        $responseDto = new ResponseDto();

        // 参数校验数组, 当前登录用户是否有权限暂不验证，后面统一处理
        $rules = [
            'order_id' => 'required|integer|min:1'
        ];
        $validate = Validator::make($request, $rules);
        if ($validate->fails()) {
            $error_list = $validate->errors()->all();
            $responseDto->status = ErrorMsg::PARAM_ERROR;
            $responseDto->msg = implode("\r\n", $error_list);
            return $responseDto;
        }
        // 从详情获取 repeat_id 值
        $v_detail = $this->theRepository->getInfoById($id);
        if (!$v_detail || !isset($v_detail['repeat_id'])) {
            ErrorMsg::FillResponseAndLog($responseDto, ErrorMsg::DATA_NOT_EXISTS);
            return $responseDto;
        }

        // 然后搜索相同的 repeat_id 即可
        $request = [
            'repeat_id' => $v_detail['repeat_id'],
        ];

        return self::getList($request);
    }

}
