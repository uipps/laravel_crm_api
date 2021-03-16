<?php
/**
 * PromotionsController
 * @author dev@xhat.com
 * @since 2020-03-10
 */
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\CommonController;
use App\Models\Admin\Promotions;
use App\Models\Admin\PromotionsHistory;
use App\Models\OrderPreSale\Order;
use App\Services\Admin\PromotionsService;


class PromotionsController extends CommonController
{
    protected $theService;

    public function __construct() {
        $this->theService = new PromotionsService();
        parent::__construct();
    }

    public function index() {
        return $this->response_json($this->theService->getList());
    }

    public function store() {
        return $this->response_json($this->theService->addOrUpdate());
    }

    public function show($id) {
        return $this->response_json($this->theService->detail($id));
    }

    public function destroy($id) {
        return $this->response_json($this->theService->delete($id));
    }

    public function update($id) {
        return $this->response_json($this->theService->updateOne($id));
    }

    public function getListActive() {
        return $this->response_json($this->theService->getListActive());
    }

    public function order_promotions() {
        $promotion = Promotions::with('promotion_rules')->where('status', 1)->orderByDesc('id')->get()->toArray();
        
        $orderId = request()->input('order_id');
        if($orderId){
            $order = Order::findOrFail($orderId);
            $order->load('goods_info');
            if(!$order->goods_info){
                return $this->wrapResponse();
            }
    
            $historyIds = [];
            foreach($order->goods_info as $info){
                if($info->promotions_info){
                    $arr = json_decode($info->promotions_info, true);
                    foreach($arr as $item){
                        $historyIds[$item['history_id']] = $item['history_id'];
                    }
                }
            }
            
            $list = PromotionsHistory::whereIn('id', $historyIds)->get();
            $history = [];
            foreach($list as $item) {
                if(!$item->promotions_detail){
                    $history[$item['promotions_id']] = '';
                    continue;
                }

                $detail = json_decode($item->promotions_detail, true);
                foreach($detail['promotion_rules'] as &$rule) {
                    $rule['rule_id'] = -1; //表示旧的活动规则
                }
                $history[$item['promotions_id']] = $detail;

            }
            $history = array_values($history);
    
            $promotion = array_merge($promotion, $history);
        }

        $ret['list'] = $promotion;

        return $this->wrapResponse($ret);

    }
}
