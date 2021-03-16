<?php
/**
 * OrderDistributeController
 * @author dev@xhat.com
 * @since 2020-03-10
 */
namespace App\Http\Controllers\OrderPreSale;

use App\Http\Controllers\CommonController;
use App\Services\OrderPreSale\OrderDistributeService;


class OrderDistributeController extends CommonController
{
    protected $theService;

    public function __construct() {
        $this->theService = new OrderDistributeService();
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

    public function distributedList() {
        return $this->response_json($this->theService->distributedList());
    }

    public function distributeNotList() {
        return $this->response_json($this->theService->distributeNotList());
    }

    public function distributeOrder() {
        request()->merge([
            'request_job_type' => 1,
        ]);
        return $this->response_json($this->theService->distributeOrder());
    }

}
