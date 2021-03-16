<?php
/**
 * OrderCancelController
 * @author dev@xhat.com
 * @since 2020-03-10
 */
namespace App\Http\Controllers\OrderPreSale;

use App\Http\Controllers\CommonController;
use App\Services\OrderPreSale\OrderCancelService;


class OrderCancelController extends CommonController
{
    protected $theService;

    public function __construct() {
        $this->theService = new OrderCancelService();
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

    public function getCancelOrderNoDealwith() {
        return $this->response_json($this->theService->getCancelOrderNoDealwith());
    }

    public function getAskForCancelDealwithSucc() {
        return $this->response_json($this->theService->getAskForCancelDealwithSucc());
    }

    public function getAskForCancelDealwithFail() {
        return $this->response_json($this->theService->getAskForCancelDealwithFail());
    }

    public function getAskForCancelFinish() {
        return $this->response_json($this->theService->getAskForCancelFinish());
    }

    // 归档操作
    public function placeOnOrder() {
        return $this->response_json($this->theService->placeOnOrder());
    }

    // 选择源订单列表
    public function getOptionalOrder() {
        return $this->response_json($this->theService->getOptionalOrder());
    }

}
