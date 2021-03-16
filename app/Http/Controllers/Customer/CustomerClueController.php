<?php
/**
 * CustomerClueController
 * @author dev@xhat.com
 * @since 2020-03-10
 */
namespace App\Http\Controllers\Customer;

use App\Http\Controllers\CommonController;
use App\Services\Customer\CustomerClueService;


class CustomerClueController extends CommonController
{
    protected $theService;

    public function __construct() {
        $this->theService = new CustomerClueService();
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

    public function distributeNotList() {
        return $this->response_json($this->theService->distributeNotList());
    }

    public function distributedList() {
        return $this->response_json($this->theService->distributedList());
    }

    public function noDealwithList() {
        return $this->response_json($this->theService->noDealwithList());
    }

    public function dealwithList() {
        return $this->response_json($this->theService->dealwithList());
    }

    public function finished() {
        return $this->response_json($this->theService->finished());
    }

    public function distribute() {
        return $this->response_json($this->theService->distribute());
    }

    public function getAbleClue() {
        return $this->response_json($this->theService->getAbleClue());
    }

}
