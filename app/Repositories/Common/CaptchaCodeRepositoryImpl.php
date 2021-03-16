<?php

namespace App\Repositories\Common;


class CaptchaCodeRepositoryImpl
{
    const CODE_ERROR_CORRECT = 0; // 验证码正确
    const CODE_ERROR_OVERDUE = 1; // 验证码已过期
    const CODE_ERROR_FAILED = 2; // 验证码错误
    const CODE_ERROR_OVER_TIMES = 3; // 验证码重试超次数

    // 图片类型取值，目前支持如下几种
    protected $type_list = [
        'default',
        'math',
        'flat',
        'mini',
        'inverse',
    ];

    // 返回json数据
    public function getCaptchaCode4Api(string $type = 'math') {
        // 返回 Content-Type: application/json， 包含3个字段：sensitive:key:img:
        $json_data = app('captcha')->create($type, true);
        return $json_data;
    }

    // 校验，返回 bool:true;false
    public function checkCaptchaCode4Api($captcha_code, $captcha_key) {
        return app('captcha')->check_api($captcha_code, $captcha_key);
        // captcha_api_check($captcha_code, $captcha_key) 也可
    }

    /**
     * 以下方法暂时用不上，因此注释掉

    // 直接返回图片，Content-Type: image/png
    public function getCaptchaPng(string $type = 'math') {
        // 返回 Content-Type: image/png
        $png_img = app('captcha')->create($type);
        return $png_img;
    }

    // 返回 Content-Type: text/html; 为图片地址，其值如： http://{host}/captcha/math?AlwTWxVU
    public function getCaptchaSrc(string $type = 'math') {
        $src_string = app('captcha')->src($type);
        return $src_string;
    }

    // 返回 Content-Type: text/html; 为html代码，其值如: <img src="http://{host}/captcha/math?QoopWs96" >
    public function getCaptchaImg(string $type = 'math') {
        $html = app('captcha')->img($type);
        return $html;
    }*/
}
