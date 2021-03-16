<?php

namespace App\Services\Common;

use App\Dto\ResponseDto;
use App\Libs\Utils\ErrorMsg;
use App\Services\BaseService;


class UploadFileService extends BaseService
{
    const IMG_TYPE = ['gif', 'jpg', 'jpeg', 'png'];

    public function __construct() {}

    public function uploadPic() {
        $responseDto = new ResponseDto();

        $fileInfo = request()->file('file');
        if(!$fileInfo || !$fileInfo->isValid()){
            $responseDto->status = ErrorMsg::UNKNOWN_ERROR;
            $responseDto->msg = 'file large than 20M';
            return $responseDto;
        }
        //$this->fileLog($fileInfo);

        $fileType = $fileInfo->getClientOriginalExtension();
        $fileType = strtolower($fileType);
        if(!in_array($fileType, self::IMG_TYPE)) {
            $responseDto->status = ErrorMsg::UNKNOWN_ERROR;
            $responseDto->msg = 'upload file must be gif, jpg, jpeg, png';
            return $responseDto;
        }

        $uniqid = session_create_id('file');

        $s3 = \App::make('aws')->createClient('s3');
        $ret = $s3->putObject(array(
            'Bucket'     => config('aws.bucket'),
            'Key'        => $uniqid,
            'ContentType' => $fileInfo->getClientMimeType(),
            'Body'      => file_get_contents($fileInfo->getRealPath()),
        ));

        $result = $ret->toArray();
        $result['FileName'] = $fileInfo->getClientOriginalName();
        $result['FileType'] = $fileType;

        $responseDto->data = $result;
        return $responseDto;
    }

    public function getPic($fileKey) {
        $s3 = \App::make('aws')->createClient('s3');
        $url = $s3->getObjectUrl(config('aws.bucket'), $fileKey);
        $client = new \GuzzleHttp\Client();
        $res = $client->request('GET', $url);
        return $res;
    }

    private function fileLog($fileInfo){
        $fileLog = "上传文件名: " . $fileInfo->getClientOriginalName() .PHP_EOL;
        $fileLog .= "文件类型: " . $fileInfo->getClientOriginalExtension() . PHP_EOL;
        $fileLog .= "文件大小: " . ($fileInfo->getClientSize() / 1024) . " kB".PHP_EOL;
        $fileLog .= "文件临时存储的位置: " . $fileInfo;
        \Log::info($fileLog);
    }
}
