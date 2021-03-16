<?php
/**
 * OrderAbnormalController
 * @author dev@xhat.com
 * @since 2020-03-10
 */
namespace App\Http\Controllers\OrderPreSale;

use App\Http\Controllers\CommonController;
use App\Services\OrderPreSale\OrderAbnormalService;


class OrderAbnormalController extends CommonController
{
    protected $theService;

    public function __construct() {
        $this->theService = new OrderAbnormalService();
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

    public function shipping($id) {
        return $this->response_json($this->theService->shipping($id));
    }

    public function getAbnormalNoDealwith() {
        return $this->response_json($this->theService->getAbnormalNoDealwith());
    }

    public function getAbnormalDealwith() {
        return $this->response_json($this->theService->getAbnormalDealwith());
    }

}
