<?php
/**
 * 图片验证码，可设置过期时间;
 * 校验的时候，记录同一个key最大错误次数，超过则返回错误
 * @2020-03-03
 */

namespace App\Services\Common;

use App\Repositories\Common\CaptchaCodeRepository;
use App\Services\BaseService;
use App\Dto\VerifyCodeDto;
use Illuminate\Support\Facades\Validator;
use App\Dto\ResponseDto;
use App\Libs\Utils\ErrorMsg;


class CaptchaCodeService extends BaseService
{
    protected $theRepository;

    public function __construct() {
        $this->theRepository = new CaptchaCodeRepository();
    }

    public function getCaptchaCode() {
        $responseDto = new ResponseDto();

        // 获取数据
        $img_data = new VerifyCodeDto();

        // 返回 Content-Type: application/json， 包含3个字段：sensitive:key:img:
        $img_create_api = $this->theRepository->getCaptchaCode4Api('math');
        $img_data->Assign($img_create_api);

        date_default_timezone_set('Asia/Shanghai'); // 强制转成东八区
        $img_data->timezone = 8;
        $img_data->expired_at = date('Y-m-d H:i:s', time() + $this->theRepository::CACHE_EXPIRE);

        $responseDto->data = $img_data;

        return $responseDto;
    }

    public function checkCaptchaCode($request=null) {
        if (!$request) $request = request()->all();
        // 返回数据
        $responseDto = new ResponseDto();

        // 参数校验
        $rules = [
            'vcode' => 'required|string|min:1',
            'key' => 'required|string|min:10',
        ];
        $validate = Validator::make($request, $rules);
        if ($validate->fails()) {
            $error_list = $validate->errors()->all();
            $responseDto->status = ErrorMsg::PARAM_ERROR;
            $responseDto->msg = implode("\r\n", $error_list);
            return $responseDto;
        }

        // 进行代码验证，返回有4个取值，分别是 0：验证通过； 1：验证码已过期，请刷新重试；2: 验证码错误 ; 3：错误次数超限
        $check_result = $this->theRepository->checkCaptchaCode4Api($request['vcode'], $request['key']);
        switch ($check_result) {
            case $this->theRepository::CODE_ERROR_CORRECT:
                // 正确，其他错误分别返回不同错误提示
                break;
            case $this->theRepository::CODE_ERROR_OVERDUE:
                ErrorMsg::FillResponseAndLog($responseDto, ErrorMsg::AUTHCODE_overdue);
                break;
            case $this->theRepository::CODE_ERROR_FAILED:
                ErrorMsg::FillResponseAndLog($responseDto, ErrorMsg::AUTHCODE_ERROR);
                break;
            default:
                //$this->theRepository::CODE_ERROR_OVER_TIMES:
                ErrorMsg::FillResponseAndLog($responseDto, ErrorMsg::AUTHCODE_throttle);
                break;
        }
        return $responseDto;
    }
}
