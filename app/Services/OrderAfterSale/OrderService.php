<?php

namespace App\Services\OrderAfterSale;

use App\Dto\CountryOrderDto;
use App\Dto\MainPanelDto;
use App\Dto\OrderOptRecordDto;
use App\Dto\OrderStatsPreSaleDto;
use App\Libs\Utils\ErrorMsg;
use App\Dto\DataListDto;
use App\Dto\ResponseDto;
use App\Dto\OrderDto;
use App\Models\Admin\User;
use App\Models\OrderPreSale\Order;
use App\Repositories\Admin\CountryRepository;
use App\Repositories\OrderPreSale\OrderOptRecordRepository;
use App\Repositories\OrderPreSale\OrderRepository;
use App\Repositories\Admin\UserRepository;
use App\Services\BaseService;
use Illuminate\Support\Facades\Validator;
use App\Repositories\OrderPreSale\OrderDetailRepository;
use App\Dto\OrderDetailDto;
use function PHPSTORM_META\elementType;


class OrderService extends BaseService
{
    protected $theRepository;
    protected $departmentRepository;
    protected $orderDetailRepository; // 就是商品信息

    public function __construct()
    {
        $this->theRepository = new OrderRepository();

    }

    // 控制面板，订单统计
    public function mainPanel() {
        // 参数接收
        $request = request()->all();
        $responseDto = new ResponseDto();

        $rules = [
            'month' => 'sometimes|integer|min:1'
        ];
        $validate = Validator::make($request, $rules);
        if ($validate->fails()) {
            $error_list = $validate->errors()->all();
            $responseDto->status = ErrorMsg::PARAM_ERROR;
            $responseDto->msg = implode("\r\n", $error_list);
            return $responseDto;
        }

        $start_time = 0; //date('Y-m-01 00:00:00', strtotime('-3 month'));   // TODO 为了有数据，时间放宽
        $end_time = 0; // date('Y-m-01 00:00:00', strtotime('+1 month'));
        if (isset($request['month'])) {
            $start_time = date('Y-m-d H:i:s', strtotime(date('Y') . '-' . $request['month'] . '-01 00:00:00'));
            $end_time = date('Y-m-d H:i:s', strtotime(date('Y') . '-' . ($request['month'] + 1) . '-01 00:00:00'));
        }

        // 主管能看到本部门的全部列表；员工只能看到自己的
        $login_user_info = self::getCurrentLoginUserInfo();
        // 如果是管理员，能看到所有
        $child_dept_list = [];
        if (1 != $login_user_info['role_id']) {
            if (User::LEVEL_ADMIN == $login_user_info['level']) {
                // 主管能看到本部门的全部列表
                $child_dept_list = parent::getChildrenDepartmentByDeptId($login_user_info['department_id']);

            } else if (User::LEVEL_STAFF == $login_user_info['level']) {
                // 员工只能看到自己的
                if (!isset($request['pre_sale_id'])) $request['pre_sale_id'] = $login_user_info['id'];
            }
        }

        $data = new MainPanelDto();

        // 获取该时间段，所有的订单， 然后按照国家归类统计
        $order_data = $this->theRepository->getOrderByUserAndTime($login_user_info, $child_dept_list, [$start_time, $end_time]);
        if (!$order_data) {
            $responseDto->data = $data;
            return $responseDto;
        }

        // 依据身份，显示不同数据，如果是员工，总订单数是分配到的订单总是，包括已经审核完和未审核的，audit_status字段判断
        $data_arr = [];
        $orderout_by_country = [];
        if (User::LEVEL_STAFF == $login_user_info['level']) {
            // 员工只能看到自己的
        } else {
            // 主管或管理员，看到手下的完成情况
        }
        $data_arr['order_total'] = 0;
        $data_arr['order_finished'] = 0;
        $data_arr['order_unfinished'] = 0;

        $data_arr['order_upsales'] = 0;
        $data_arr['orderout_signed'] = 0;
        $data_arr['orderout_rejected'] = 0;
        $data_arr['orderout_delivering'] = 0;
        $data_arr['orderout_sign_rate'] = 0; // 计算得出

        foreach ($order_data as $one_order) {
            if (1 == $one_order['audit_status']) {
                // 已经审核完成
                $data_arr['order_finished'] += 1;
            } else {
                // 未审核完成
                $data_arr['order_unfinished'] += 1;
            }

            // 按照国家ID归类
            //if (!isset($orderout_by_country[$one_order['country_id']])) {
                $orderout_by_country[$one_order['country_id']][] = $one_order;
            //}
        }
        $data_arr['order_total'] = $data_arr['order_finished'] + $data_arr['order_unfinished'];

        $country_all = (new CountryRepository())->getAllCountry(); // 国家数据

        // 按照国家归类，多个国家的数据
        if ($orderout_by_country) {
            $l_orderout_by_country = [];

            foreach ($orderout_by_country as $country_id => $order_list) {
                $country_order_arr = [];
                $country_order_arr['country_id'] = $country_id;
                $country_order_arr['country_name'] = $country_all[$country_id]['display_name'];

                $country_order_arr['order_upsales'] = 0;
                $country_order_arr['orderout_signed'] = 0;
                $country_order_arr['orderout_rejected'] = 0;
                $country_order_arr['orderout_delivering'] = 0;
                $country_order_arr['orderout_sign_rate'] = 0; // 计算得出

                // 统计对应数量: 已签收 / Upsales订单 / 拒收 / 未签收 / 签收率
                foreach ($order_list as $one_order) {
                    // Upsales订单
                    if (in_array($one_order['pre_opt_type'], [13, 14])) {
                        $country_order_arr['order_upsales'] += 1;
                        $data_arr['order_upsales']++;     // 合计
                    }

                    if (9 == $one_order['shipping_status']) {
                        // 已签收
                        $country_order_arr['orderout_signed'] += 1;
                        $data_arr['orderout_signed']++;     // 合计
                    } else if (16 == $one_order['shipping_status']) {
                        // 拒收
                        $country_order_arr['orderout_rejected'] += 1;
                        $data_arr['orderout_rejected']++;
                    } else if (in_array($one_order['shipping_status'], [8, 30])) {
                        // 配送中
                        $country_order_arr['orderout_delivering'] += 1;
                        $data_arr['orderout_delivering']++;
                    }
                }
                $l_total = $country_order_arr['orderout_delivering'] + $country_order_arr['orderout_rejected'] + $country_order_arr['orderout_signed'];
                if (0 != $l_total) {
                    $country_order_arr['orderout_sign_rate'] = bcdiv(100*$country_order_arr['orderout_signed'], $l_total, 2);
                }

                $country_order = new CountryOrderDto();
                $country_order->Assign($country_order_arr);
                $l_orderout_by_country[] = $country_order;
            }
            $data->orderout_by_country = $l_orderout_by_country;

            // 总签收率
            $l_total = $data_arr['orderout_delivering'] + $data_arr['orderout_rejected'] + $data_arr['orderout_signed'];
            if (0 != $l_total) {
                $data_arr['orderout_sign_rate'] = bcdiv(100*$data_arr['orderout_signed'], $l_total, 2);
            }
        }

        $data->Assign($data_arr);

        $responseDto->data = $data;
        return $responseDto;
    }

}
