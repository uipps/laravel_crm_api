<?php
/**
 * OrderReportController
 * @author dev@xhat.com
 * @since 2020-03-10
 */
namespace App\Http\Controllers\OrderAfterSale;

use App\Http\Controllers\CommonController;
use App\Http\Resources\OrderReportResource;
use App\ModelFilters\OrderReportFilter;
use App\Models\OrderPreSale\Order;
use App\Models\OrderPreSale\OrderManual;
use App\Services\Common\FieldMapService;
use App\Services\OrderAfterSale\OrderReportService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

class OrderReportController extends CommonController
{
    protected $theService;

    public function __construct() {
        $this->theService = new OrderReportService();
        parent::__construct();
    }

    public function getListOrExport() {
        return $this->response_json($this->theService->getListOrExport());
    }

    public function list(Request $request) {
        // $manual = OrderManual::joinOrderDepartment();
        // $query = Order::join($manual, '');
        $query = Order::joinManualDepartment()
        ->whereIn('order_type', [3])
        ->whereIn('order_source', [4, 15]);
        $with = [
            'goods_info',
            'country',
            'language',
            'pre_sale',
            'pre_opt',
            'department',
            'after_sale',
        ];


        $query->with($with);
        $query->filter($request->input(), OrderReportFilter::class);

        if($request->input('request_action') == 'download'){
            $field_list = request()->input('field_list', $this->exportColumns());
            $field_list = is_array($field_list) ? $field_list : json_decode($field_list, true);

            // 批量导出
            exportCsv($field_list, function()use($query){
                $fieldMap = (new FieldMapService())->fieldsMap()->data;
                $sms_verify = Arr::get($fieldMap,'order.sms_verify_status.enum');
                $shipping_status = Arr::get($fieldMap,'order.shipping_status.enum');
                $auditStatus = Arr::get($fieldMap,'order.audit_status.enum');
                $orderStatus = Arr::get($fieldMap,'order.order_status.enum');
                $orderSecondType = Arr::get($fieldMap,'order.order_second_type.enum');

                $limit = 1000;
                $ret = $query->dealList()->paginateFilter($limit);
                $ret = OrderReportResource::collection($ret)->resource;

                $nextPage = request()->input('page', 1) + 1;
                request()->merge(['page' => $nextPage]);

                $ret = json_decode($ret->toJson(), true);
                $ret = Arr::get($ret, 'data');
                foreach($ret as &$item){
                    $item['sms_verify_status'] = Arr::get($sms_verify, $item['sms_verify_status']);
                    $item['shipping_status'] = Arr::get($shipping_status, $item['shipping_status']);
                    $item['audit_status'] = Arr::get($auditStatus, $item['audit_status']);
                    
                    $item['order_status'] = Arr::get($orderStatus, $item['order_status']);
                    $item['order_second_type'] = Arr::get($orderSecondType, $item['order_second_type']);

                    $goodsInfo = Arr::get($item, 'goods_info');
                    $item['internal_name'] = $goodsInfo ? implode(PHP_EOL, array_column($goodsInfo,'internal_name')) : '';
                    $item['sku'] = $goodsInfo ? implode(PHP_EOL, array_column($goodsInfo,'sku')) : '';
                    $item['num'] = $goodsInfo ? array_sum(array_column($goodsInfo,'num')) : 0;

                }
                return $ret;
            });

        }else{
            $ret = $query->dealList()->paginateFilter($request->input('limit'));
            $ret = OrderReportResource::collection($ret)->resource;
            
            return $this->wrapResponse($ret);
        }

        
    }

    // 导出字段
    private function exportColumns(){
        $ret = [
            'order_no'      => '订单号',
            'created_time'  => '订单时间',
            'country_name'  => '国家',
            'language_name' => '语言',
        ];
        return $ret;
    }
}
