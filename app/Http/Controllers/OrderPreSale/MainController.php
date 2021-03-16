<?php

namespace App\Http\Controllers\OrderPreSale;

use App\Http\Controllers\CommonController;
use App\Services\OrderPreSale\OrderService;
use Illuminate\Http\Request;
use App\Models\Admin\UserCustomerReport;
use App\Models\Admin\UserOrderReport;
use Illuminate\Support\Arr;
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
        $orderReport = UserOrderReport::query();

        // 2表示售后
        $orderReport->where('user_type', 1);

        $auth = Auth('api')->user();
        if($auth->level > 0 && $auth->id > 1){
            if($auth->level == 1){
                $orderReport->where('department_id', $auth->department_id);
            }else{
                $orderReport->where('user_id', $auth->id);
            }
        }
        

        $startTime = $request->input('start_time');
        $endTime = $request->input('end_time');

        if($startTime) {
            $orderReport->where('date', '>=', date('Ymd', $startTime));
        }

        if($endTime) {
            $orderReport->where('date', '<=', date('Ymd', $endTime));
        }

        $selects = [
            DB::raw('sum(order_total_num) as order_total'),
            DB::raw('sum(order_finished_num) as order_finished'),
            DB::raw('sum(order_unfinished_num) as order_unfinished'),
            DB::raw('sum(order_upsales_num) as order_upsales'),
            DB::raw('sum(order_received_num) as orderout_signed'),
            DB::raw('sum(order_refused_num) as orderout_rejected'),
            DB::raw('sum(order_unreceived_num) as orderout_delivering'),
            DB::raw('ROUND(sum(order_received_num)/sum(order_total_num),4) as orderout_sign_rate'),
        ];

        $orderCountryReport = clone $orderReport;

        $orderData = $orderReport->select($selects)->first()->toArray();

        $selects[] = 'country_id';

        $orderCountry = $orderCountryReport->with('country')->select($selects)->groupBy('country_id')->get()->sortBy('country_id')->keyBy('country_id')->toArray();
        foreach ($orderCountry as &$item){
            $item['country_name'] = Arr::get($item, 'country.display_name');
        }

        $orderData['orderout_by_country'] = array_values($orderCountry);

        return $this->wrapResponse($orderData);
    }
}
