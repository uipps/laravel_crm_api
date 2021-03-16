<?php

namespace App\Http\Controllers\Admin;

use App\Services\Admin\UserService;
use App\Http\Controllers\CommonController;
use App\Models\Admin\User;

class UserController extends CommonController
{
    protected $theService;

    public function __construct() {
        $this->theService = new UserService();
        $this->middleware('auth:api', ['except' => ['loginAdmin', 'parseJwt']]);
        parent::__construct();
    }

    // 自定义登录验证
    public function loginAdmin() {
        return $this->response_json($this->theService->loginAdmin());
    }

    public function logout() {
        return $this->response_json($this->theService->logout());
    }

    public function refresh() {
        return $this->response_json($this->theService->refresh());
    }

    public function me() {
        return $this->response_json($this->theService->me());
    }

    public function show($id) {
        return $this->response_json($this->theService->getById($id));
    }

    public function setPassword() {
        return $this->response_json($this->theService->setPassword());
    }

    public function store() {
        return $this->response_json($this->theService->addOrUpdate());
    }

    public function index() {
        return $this->response_json($this->theService->getList());
    }

    // 临时用于解析jwttoken数据
    public function parseJwt() {
        return $this->response_json($this->theService->parseJwt());
    }

    // 员工页面操作事件通知服务端，用于签到等；包括关闭页面事件、20分钟超时无操作；
    public function pageAction() {
        return $this->response_json($this->theService->pageAction());
    }

    public function destroy($id) {
        $ret = User::find($id)->delete();
        return $this->wrapResponse();
        // return $this->response_json($this->theService->delete($id));
    }

    public function update($id) {
        return $this->response_json($this->theService->updateOne($id));
    }

    // 开始接单/停止接单
    public function receiveOrder() {
        return $this->response_json($this->theService->receiveOrder());
    }

    // 切换语言，记录到数据表，以后可登录不同设备
    public function changeWebLanguage() {
        return $this->response_json($this->theService->changeWebLanguage());
    }

    // 我的下属员工
    public function getMySubordinate() {
        return $this->response_json($this->theService->getMySubordinate());
    }

}
