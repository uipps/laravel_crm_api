<?php

namespace App\Repositories\Common;


class CaptchaCodeRepository extends CaptchaCodeRepositoryImpl
{
    const CACHE_EXPIRE = 300;            // 单位秒, 过期时间 5分钟
    const VERIFY_CODE_ERROR_MAX_NUM = 3; // 同一个key验证错误数3次，就失效

    // 图片验证码错误次数记录
    private static function GetVKeyCacheKey($vkey) {
        return 'verify-captcha-code-key-' . $vkey;
    }

    // 图片验证码错误次数记录
    private static function GetVerifyErrorNumCacheKey($vkey) {
        return 'verify-captcha-code-error-numbers-' . $vkey;
    }

    // 5分钟内错误超过3次，需要记录错误次数并返回
    public function addVerifyErrorNumByKey($vkey) {
        // 从cache获取数据
        $cache_key = self::GetVerifyErrorNumCacheKey($vkey);
        $cached_result = \Cache::get($cache_key);
        if ($cached_result) {
            // 计数加1
            \Cache::increment($cache_key);
            return $cached_result + 1;
        }
        // 错误1次，种cache
        $cached_result = 1;
        \Cache::put($cache_key, $cached_result, self::CACHE_EXPIRE);

        return $cached_result;
    }

    // 获取某vkey错误次数
    public function getVerifyErrorNumsByKey($vkey) {
        // 从cache获取数据
        $cache_key = self::GetVerifyErrorNumCacheKey($vkey);
        $cached_result = \Cache::get($cache_key);
        if ($cached_result)
            return $cached_result;
        return 0;
    }

    // 错误次数是否超过设定值
    public function isOverThrottleCodeByKey($vkey) {
        // 从cache获取数据
        $cache_key = self::GetVerifyErrorNumCacheKey($vkey);
        $cached_result = \Cache::get($cache_key);
        if (!$cached_result)
            return 0;
        return ($cached_result >= self::VERIFY_CODE_ERROR_MAX_NUM) ? 1 : 0;
    }

    /**
     * 生成验证码，并设定过期时间
     *
     * @param string $config 验证码类型：default math mini flat 等几种取值
     * @param int $captcha_expire 过期时间
     * @return  array
     *  $captchaParams =  [
     *      "sensitive" => true,
     *      "key" => "$2y$10$6WD3PFN8ssjWzAKhaBdZ6u.1UC50pOPiTOrRfz4PzFCmSmdJY9xt6",// 验证码的hash值
     *      "img" => "data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAHgAAAAkCAYAAABCKP5eAAAA",// base64后的图片
     *  ];
     */
    public function getCaptchaCode4Api(string $config = 'default')
    {
        $captchaParams = parent::getCaptchaCode4Api($config);
        $cache_key = self::GetVKeyCacheKey($captchaParams['key']);
        // 缓存起来
        \Cache::put($cache_key, 1, self::CACHE_EXPIRE);
        return $captchaParams;
    }

    /**
     * 校验验证码
     *
     * @param string $captcha_code 验证码
     * @param string $captcha_key 缓存key
     * @param string $errDo 错误处理方式 1 throws 2直接返回错误
     * @return  int: 0：验证通过； 1：验证码已过期，请刷新重试；2: 验证码错误 ; 3：错误次数超限
     */
    public function checkCaptchaCode4Api($captcha_code, $captcha_key)
    {
        $cache_key = self::GetVKeyCacheKey($captcha_key);
        $cached_result = \Cache::get($cache_key);

        // 1. cache中没有，则说明过期，或不存在（不存在也当成过期处理）
        if (!$cached_result) {
            return parent::CODE_ERROR_OVERDUE; // 验证码已过期
        }
        // 2. 检查一下重试次数是否超限
        if (self::isOverThrottleCodeByKey($captcha_key)) {
            // \Cache::forget($cache_key); // 重试多次暂不删除缓存，5分钟自动过期
            return parent::CODE_ERROR_OVER_TIMES; // 错误次数超限
        }
        // 3. 验证码是否正确
        if (!parent::checkCaptchaCode4Api($captcha_code, $captcha_key)) {
            self::addVerifyErrorNumByKey($captcha_key);
            return parent::CODE_ERROR_FAILED; // 验证码错误
        }

        return parent::CODE_ERROR_CORRECT; // 验证码正确
    }
}
