<?php
/**
 * OrderController
 * @author dev@xhat.com
 * @since 2020-03-10
 */
namespace App\Http\Controllers\OrderPreSale;

use App\Http\Controllers\CommonController;
use App\Services\OrderPreSale\OrderService;
use App\Logics\OrderPreSale\OrderLogic;


class OrderController extends CommonController
{
    protected $theService;
    protected $orderLogic;

    public function __construct() {
        $this->theService = new OrderService();
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

    public function customerOrderList() {
        return $this->response_json($this->theService->customerOrderList());
    }

    public function customerIdOrderList($customer_id) {
        return $this->response_json($this->theService->customerIdOrderList($customer_id));
    }

    public function updateOrderByType($order_id, $put_type) {
        $this->orderLogic = new orderLogic();   // 用逻辑层调用不同服务
        return $this->response_json($this->orderLogic->updateOrderByType($order_id, $put_type));
    }
    // 订单详情-统一接口
    public function detailByType($order_id, $put_type) {
        $this->orderLogic = new orderLogic();   // 用逻辑层调用不同服务
        return $this->response_json($this->orderLogic->detailByType($order_id, $put_type));
    }

    public function getListByUser() {
        return $this->response_json($this->theService->getListByUser());
    }

    // 员工订单转移给其他员工
    public function orderTransfer() {
        return $this->response_json($this->theService->orderTransfer());
    }
}
