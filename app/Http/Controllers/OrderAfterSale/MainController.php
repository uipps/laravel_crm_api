<?php

namespace App\Http\Controllers\OrderAfterSale;

use App\Http\Controllers\CommonController;
use App\Models\Admin\UserCustomerReport;
use App\Models\Admin\UserOrderReport;
use App\Services\OrderAfterSale\OrderService;
use Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MainController extends CommonController
{
    protected $userService;

    public function __construct() {
        $this->orderService = new OrderService();
        parent::__construct();
    }

    // 自定义登录验证
    public function mainPanel() {
        return $this->response_json($this->orderService->mainPanel());
    }

    /**
     * 控制面板
     */
    public function dashboard(Request $request) {
        //
        $customerReport = UserCustomerReport::query(); //创建query对象
        $orderReport = UserOrderReport::query();

        // 2表示售后
        $customerReport->where('user_type', 2);
        $orderReport->where('user_type', 2);

        $auth = Auth('api')->user();
        if($auth->level > 0 && $auth->id > 1){
            if($auth->level == 1){
                $customerReport->where('department_id', $auth->department_id);
                $orderReport->where('department_id', $auth->department_id);
            }else{
                $customerReport->where('user_id', $auth->id);
                $orderReport->where('user_id', $auth->id);
            }
        }
        

        $startTime = $request->input('start_time');
        $endTime = $request->input('end_time');

        if($startTime) {
            $customerReport->where('date', '>=', date('Ymd', $startTime));
            $orderReport->where('date', '>=', date('Ymd', $startTime));
        }

        if($endTime) {
            $customerReport->where('date', '<=', date('Ymd', $endTime));
            $orderReport->where('date', '<=', date('Ymd', $endTime));
        }

        $selects = [
            DB::raw('sum(if(customer_level=1, customer_num, NULL)) as customer_level_a'),
            DB::raw('sum(if(customer_level=2, customer_num, NULL)) as customer_level_b'),
            DB::raw('sum(if(customer_level=3, customer_num, NULL)) as customer_level_c'),
            DB::raw('sum(if(customer_level=4, customer_num, NULL)) as customer_level_d'),
            DB::raw('sum(customer_num) as customer_all'),
        ];

        $customerData = $customerReport->select($selects)->first()->toArray();
        

        $selects = [
            'country_id',
            DB::raw('sum(order_total_num) as order_total_num'),
            DB::raw('sum(order_received_num) as order_received_num'),
            DB::raw('sum(order_refused_num) as order_refused_num'),
            DB::raw('sum(order_unreceived_num) as order_unreceived_num'),
            DB::raw('sum(order_received_money) as order_received_money'),
            DB::raw('ROUND(sum(order_received_num)/sum(order_total_num),4) as received_rate'),
        ];

        $orderData = $orderReport->with('country')->select($selects)->groupBy('country_id')->get()->sortBy('country_id')->keyBy('country_id')->toArray();
 
        $ret = [
            'customer' => $customerData,
            'order' => array_values($orderData)
        ];
        return $this->wrapResponse($ret);
    }
}
