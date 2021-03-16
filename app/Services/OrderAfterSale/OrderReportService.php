<?php

namespace App\Services\OrderAfterSale;

use App\Dto\OrderDetailDto;
use App\Dto\OrderDto;
use App\Dto\OrderStatsPreSaleDto;
use App\Libs\Utils\ErrorMsg;
use App\Dto\DataListDto;
use App\Dto\ResponseDto;
use App\Dto\OrderReportDto;
use App\Models\Admin\User;
use App\Models\OrderPreSale\Order;
use App\Repositories\Admin\UserRepository;
use App\Repositories\OrderPreSale\OrderDetailRepository;
use App\Repositories\OrderPreSale\OrderRepository;
use App\Repositories\OrderPreSale\OrderStatusRepository;
use App\Services\BaseService;
use Illuminate\Support\Facades\Validator;


class OrderReportService extends BaseService
{
    protected $theRepository;
    protected $departmentRepository;
    protected $orderDetailRepository;
    protected $orderStatusRepository;
    protected $login_user_info;
    protected $child_dept_list;
    protected $order_status_list;

    public function __construct() {
        $this->theRepository = new OrderRepository();
        $this->orderDetailRepository = new OrderDetailRepository();
        $this->userRepository = new UserRepository(); // 用于权限检查
    }

    public function getList() {
        $request = request()->all(); // 参数接收
        $request['job_type'] = 2;           // 强制为售后
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

        // 主管能看到本部门的全部列表；员工只能看到自己的
        $login_user_info = self::getCurrentLoginUserInfo();
        // 如果是管理员，能看到所有
        if (1 != $login_user_info['role_id']) {
            if (User::LEVEL_ADMIN == $login_user_info['level']) {
                // 主管能看到本部门的全部列表
                $child_dept_list = parent::getChildrenDepartmentByDeptId($login_user_info['department_id']);
                if (isset($request['department_id']) && $request['department_id']) {
                    $my_dept_list = self::getChildrenDepartmentByDeptId($request['department_id']);
                    $request['department_id'] = ['in', $my_dept_list];
                } else {
                    $request['department_id'] = ['in', $child_dept_list];
                }
            } else if (User::LEVEL_STAFF == $login_user_info['level']) {
                // 员工只能看到自己的, 售后只有手工单，字段为 `order_manual`.`order_sale_id`
                if (!isset($request['order_sale_id'])) $request['order_sale_id'] = $login_user_info['id'];
            }
        }

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
                $order_ids[] = $v_detail['id'];
            }
            // 通过 order_id，获取订单对应的商品详情数据
            $goods_info_list = $this->orderDetailRepository->getGoodsListByOrderIds($order_ids); // 三维数组

            // 成功，返回列表信息
            foreach ($list[$responseDto::DTO_FIELD_LIST] as $key => $v_detail) {
                $list[$responseDto::DTO_FIELD_LIST][$key] = self::getOneOrderInfo($v_detail, $goods_info_list);
            }
        }
        /*if ($list[$responseDto::DTO_FIELD_LIST]) {
            // 成功，返回列表信息
            foreach ($list[$responseDto::DTO_FIELD_LIST] as $key => $v_detail) {
                $v_info = new OrderDto();
                $v_info->Assign($this->addAllAttrName2Data($v_detail));
                $list[$responseDto::DTO_FIELD_LIST][$key] = $v_info;
            }
        }*/
        $data_list = new DataListDto();
        if (!isset($request[$responseDto::WITHOUT_ORDER_STATS]) || !$request[$responseDto::WITHOUT_ORDER_STATS]) {
            // 默认情况，所有订单列表都要带上订单统计数据；如果设置不需要携带统计数据则跳过
            $order_stat = new OrderStatsPreSaleDto();
            // $order_stat->Assign($redis_stats_data); // TODO 从redis获取数据
            $data_list->meta[$responseDto::DTO_FIELD_ORDER_STATS] = $order_stat;
        }
        $data_list->Assign($list);
        $responseDto->data = $data_list;

        return $responseDto;
    }

    // 订单详情
    private function getOneOrderInfo($v_detail, $goods_info_list, $order_opt_records=[]) {
        $order_id = $v_detail['id'];
        // 字段映射，数据表中无 submit_type 字段，但是前端需要submit_type字段；DTO修改没有作用，因为进行数组合并
        if (0 == $v_detail['audit_status'] || 41 == $v_detail['pre_opt_type']) {
            $v_detail['submit_type'] = 2;
        }
        if (isset($v_detail['audit_time']) && strtotime($v_detail['audit_time']) <= 0) $v_detail['audit_time'] = '-';
        if (isset($v_detail['opt_time']) && strtotime($v_detail['opt_time']) <= 0) $v_detail['opt_time'] = '-';
        if (isset($v_detail['order_time']) && strtotime($v_detail['order_time']) <= 0) $v_detail['order_time'] = '-';

        $v_info = new OrderDto();
        $v_info->Assign($this->addAllAttrName2Data($v_detail));

        // 商品详情赋值
        if (isset($goods_info_list[$order_id])) {
            foreach ($goods_info_list[$order_id] as $goods_val) {
                $goods_dto = new OrderDetailDto();
                $goods_dto->Assign($goods_val);
                $v_info->goods_info[] = $goods_dto; // 可能有多条商品信息，放到字段 goods_info 上
            }
        }
        return $v_info;
    }


    // 分为查询和导出两种情况
    public function getListOrExport() {
        // 导出的情况
        $request_action = request()->only('request_action');
        if ($request_action && 'download' == $request_action['request_action']) {
            $this->exportData();
            exit;
        }

        // 查询列表的情况
        return $this->getList();
    }

    // 导出字段
    public static function exportColumns(){
        $ret = [
            'order_no'      => '订单号',
            'created_time'  => '订单时间',
            'country_name'  => '国家',
            'language_name' => '语言',
            'pre_sale_name' => '售前人员',
        ];
        return $ret;
    }

    // 导出
    public function exportData() {
        $field_list = request()->only('field_list');
        if (!$field_list) {
            $field_list = self::exportColumns();
        } else {
            // TODO $field_list 拼装一下，让字段在允许的范围内
            $field_list = $field_list['field_list'];
        }
        if (!is_array($field_list)) $field_list = json_decode($field_list, true); // 改成GET请求后，需要将json字符串转成json数组
        if (!$field_list || !is_array($field_list)) {echo 'field_list params error!';exit;}

        $this->login_user_info = self::getCurrentLoginUserInfo();
        $login_user_info = $this->login_user_info;
        if (1 != $login_user_info['role_id']) {
            if (User::LEVEL_ADMIN == $login_user_info['level']) {
                // 主管能看到本部门的全部列表
                $this->child_dept_list = parent::getChildrenDepartmentByDeptId($login_user_info['department_id']);
            }
        }

        if (!$this->orderStatusRepository) $this->orderStatusRepository = new OrderStatusRepository();
        $order_status_list = $this->orderStatusRepository->getAllOrderStatus(1);
        $this->order_status_list = array_column($order_status_list, 'name', 'id');;

        request()->merge(['page' => 1]); // 导出全部，避免初始页码参数影响导出条数

        // 闭包
        \exportCsv($field_list, function(){
            $params = request()->all();
            $params['job_type'] = 2;
            $params['limit'] = 1000;    // 每次获取的条数

            $login_user_info = $this->login_user_info;
            if (1 != $login_user_info['role_id']) {
                if (User::LEVEL_ADMIN == $login_user_info['level']) {
                    // 主管能看到本部门的全部列表
                    if (isset($params['department_id']) && $params['department_id']) {
                        $my_dept_list = self::getChildrenDepartmentByDeptId($params['department_id']);
                        $params['department_id'] = ['in', $my_dept_list];
                    } else {
                        $params['department_id'] = ['in', $this->child_dept_list];
                    }
                } else if (User::LEVEL_STAFF == $login_user_info['level']) {
                    // 员工只能看到自己的, 售后只有手工单，字段为 `order_manual`.`order_sale_id`
                    if (!isset($params['order_sale_id'])) $params['order_sale_id'] = $login_user_info['id'];
                }
            }
            // 获取列表数据
            $list = $this->theRepository->getList($params);

            // 设置翻页
            $nextPage = request()->input('page', 1) + 1;
            request()->merge(['page' => $nextPage]);

            if (!$list || !$list['list']) return [];
            $ret = $list['list'];

            // 属性id赋值name，顺便获取商品信息
            $audit_status = Order::AUDIT_STATUS_LIST;
            $order_type = Order::ORDER_TYPE_LIST;
            $order_status_list = $this->order_status_list;

            $order_ids = array_column($ret, 'id');
            $goods_info_list = $this->orderDetailRepository->getGoodsListByOrderIds($order_ids); // 三维数组
            foreach ($ret as &$v_detail) {
                if (strtotime($v_detail['audit_time']) <= 0) $v_detail['audit_time'] = '-';
                if (strtotime($v_detail['opt_time']) <= 0) $v_detail['opt_time'] = '-';
                if (strtotime($v_detail['order_time']) <= 0) $v_detail['order_time'] = '-';
                $v_detail = $this->addAllAttrName2Data($v_detail);

                // 字段翻译：
                if (isset($v_detail['order_status'])) $v_detail['order_status'] = $order_status_list[$v_detail['order_status']] ?? '-';
                if (isset($v_detail['audit_status'])) $v_detail['audit_status'] = $audit_status[$v_detail['audit_status']] ?? '-';
                if (isset($v_detail['order_type'])) $v_detail['order_type'] = $order_type[$v_detail['order_type']] ?? '-';

                // 商品名称、商品数量处理 TODO
                $v_detail['internal_name'] = '';  // 商品名称
                $v_detail['sku'] = '';
                $v_detail['num'] = 0;

                // 商品名称、商品数量字段赋值
                if (isset($goods_info_list[$v_detail['id']])) {
                    foreach ($goods_info_list[$v_detail['id']] as $goods_val) {
                        $v_detail['internal_name'] .= $goods_val['internal_name'] . "\n";    // 商品内部名
                        $v_detail['sku'] .= $goods_val['sku'] . "\n";
                        $v_detail['num'] += $goods_val['num'];
                    }
                    $v_detail['internal_name'] = trim($v_detail['internal_name']);
                    $v_detail['sku'] = trim($v_detail['sku']);
                }
            }

            return $ret;
        });
        exit;
    }
}
