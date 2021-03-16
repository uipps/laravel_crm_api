<?php

namespace App\Repositories\OrderPreSale;

class OrderStatusRepository extends OrderStatusRepositoryImpl
{
    const CACHE_EXPIRE = 1;  // 单位秒，缓存时间
    const CACHE_EXPIRE_LIST = 1;

    private static function GetCacheKey($id) {
        return 'db:order_status:detail-id-' . $id;
    }

    private static function GetListCacheKey($type='all') {
        return 'db:order_status:list-type-' . $type;
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

    public function getAllOrderStatus($type='all') {
        // 先从cache获取数据
        $cache_key = self::GetListCacheKey($type);
        $cached_result = \Cache::get($cache_key);
        if ($cached_result)
            return $cached_result;

        // 再从数据库获取，获取到了则种cache
        $db_result = parent::getAllOrderStatus($type);
        if (!$db_result)
            return $db_result;
        \Cache::put($cache_key, $db_result, self::CACHE_EXPIRE_LIST);

        return $db_result;
    }
}
