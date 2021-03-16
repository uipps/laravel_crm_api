<?php
/**
 * OrderManualController
 * @author dev@xhat.com
 * @since 2020-03-10
 */
namespace App\Http\Controllers\OrderPreSale;

use App\Http\Controllers\CommonController;
use App\Services\OrderPreSale\OrderManualService;


class OrderManualController extends CommonController
{
    protected $theService;

    public function __construct() {
        $this->theService = new OrderManualService();
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

    // 补发列表
    public function getReplenishList() {
        return $this->response_json($this->theService->getReplenishList());
    }

    // 重发列表
    public function getRedeliveryList() {
        return $this->response_json($this->theService->getRedeliveryList());
    }

    // 添加补发
    public function addReplenish($id) {
        return $this->response_json($this->theService->addReplenish($id));
    }

    // 添加重发
    public function addRedelivery($id) {
        return $this->response_json($this->theService->addRedelivery($id));
    }

}
