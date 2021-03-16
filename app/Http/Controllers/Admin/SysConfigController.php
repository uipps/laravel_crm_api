<?php
/**
 * SysConfigController
 * @author dev@xhat.com
 * @since 2020-03-10
 */
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\CommonController;
use App\Services\Admin\SysConfigService;


class SysConfigController extends CommonController
{
    protected $theService;

    public function __construct() {
        $this->theService = new SysConfigService();
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

    public function getTimeoutInfo() {
        return $this->response_json($this->theService->getTimeoutInfo());
    }

}
