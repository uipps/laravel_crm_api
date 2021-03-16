<?php

namespace App\Repositories\OrderPreSale;

class OrderOptTypeRepository extends OrderOptTypeRepositoryImpl
{
    const CACHE_EXPIRE = 1;  // 单位秒，缓存时间
    const CACHE_EXPIRE_LIST = 1;

    private static function GetCacheKey($id) {
        return 'db:order_opt_type:detail-id-' . $id;
    }

    private static function GetListCacheKey($job_type) {
        return 'db:order_opt_type:list-all-' . $job_type;
    }

    private static function GetPreOptTypeCacheKey() {
        return 'db:order_opt_type:pre-order-opt-type-list';
    }

    // 通过id获取信息
    public function getInfoById($id) {
        // 先从cache获取数据
        $cache_key = self::GetCacheKey($id);
        $cached_result = \Cache::get($cache_key);
        if ($cached_result)
            return $cached_result;

        // 再从数据库获取，获取到了则种cache
        $db_result = parent::getInfoById($id);
        if (!$db_result)
            return $db_result;
        \Cache::put($cache_key, $db_result, self::CACHE_EXPIRE);

        return $db_result;
    }

    // 订单操作所有类型
    public function getAllOptType($job_type=1) {
        // 先从cache获取数据
        $cache_key = self::GetListCacheKey($job_type);
        $cached_result = \Cache::get($cache_key);
        if ($cached_result)
            return $cached_result;

        // 再从数据库获取，获取到了则种cache
        $db_result = parent::getAllOptType($job_type);
        if (!$db_result)
            return $db_result;
        \Cache::put($cache_key, $db_result, self::CACHE_EXPIRE_LIST);

        return $db_result;
    }

    // 目前就是返回固定的那几个
    public function getPreOptTypeList() {
        // 先从cache获取数据
        $cache_key = self::GetPreOptTypeCacheKey();
        $cached_result = \Cache::get($cache_key);
        if ($cached_result)
            return $cached_result;

        // 再从数据库获取，获取到了则种cache
        $db_result = parent::getPreOptTypeList();
        if (!$db_result)
            return $db_result;
        \Cache::put($cache_key, $db_result, self::CACHE_EXPIRE_LIST);

        return $db_result;
    }

}
