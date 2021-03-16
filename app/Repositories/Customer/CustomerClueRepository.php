<?php

namespace App\Repositories\Customer;

class CustomerClueRepository extends CustomerClueRepositoryImpl
{
    const CACHE_EXPIRE = 1;  // 单位秒，缓存时间

    private static function GetCacheKey($id) {
        return 'db:customer_clue:detail-id-' . $id;
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

}
