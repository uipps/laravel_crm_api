<?php

namespace App\Http\Controllers\Common;

use App\Http\Controllers\CommonController;
use App\Services\Common\UploadFileService;


class UploadFileController extends CommonController
{
    protected $theService;

    public function __construct() {
        $this->theService = new UploadFileService();
        parent::__construct();
    }

    public function uploadPic() {
        return $this->response_json($this->theService->uploadPic());
    }

    public function getPic($fileKey) {
        $res = $this->theService->getPic($fileKey);
        return response($res->getBody())
            ->header('Content-Type', $res->getHeader('content-type'))
            ->header('Access-Control-Allow-Origin', '*');
    }

    // 文件下载测试
    public function download() {
        $files = '/Users/cf/code.aliyun.com/php/www/productM_dev/template/mt23/mt23.zip';
        $name = basename($files);
        $headers = ['Content-Type'=>'application/zip;charset=utf-8'];
        return response()->download($files, $name, $headers);
    }
}
