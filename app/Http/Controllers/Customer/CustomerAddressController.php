<?php
/**
 * CustomerAddressController
 * @author dev@xhat.com
 * @since 2020-03-10
 */
namespace App\Http\Controllers\Customer;

use App\Http\Controllers\CommonController;
use App\Services\Customer\CustomerAddressService;


class CustomerAddressController extends CommonController
{
    protected $theService;

    public function __construct() {
        $this->theService = new CustomerAddressService();
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

    public function getListByCid($id) {
        return $this->response_json($this->theService->getListByCid($id));
    }

    public function getListByCidAll($id) {
        return $this->response_json($this->theService->getListByCidAll($id));
    }

}
