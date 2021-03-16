<?php

namespace App\Http\Controllers\Common;

use App\Http\Controllers\CommonController;
use App\Services\Common\CaptchaCodeService;


class CaptchaCodeController extends CommonController
{
    private $theService;

    public function __construct() {
        $this->theService = new CaptchaCodeService();
        parent::__construct();
    }

    public function getVerifyCode() {
        return $this->response_json($this->theService->getCaptchaCode());
    }

    public function checkVerifyCode() {
        return $this->response_json($this->theService->checkCaptchaCode());
    }
}
