<?php

namespace App\Repositories\Customer;

class CustomerLabelRepository extends CustomerLabelRepositoryImpl
{
    const CACHE_EXPIRE = 1;  // 单位秒，缓存时间

    private static function GetCacheKey($id) {
        return 'db:customer_label:detail-id-' . $id;
    }

    private static function GetCustomerTypeCacheKey($customer_id, $label_type) {
        return 'db:customer_label:detail-customer_id-' . $customer_id . '-label_type-' . $label_type;
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

    // 该表三个字段联合成唯一索引
    public function getInfoByCustomerIdType($customer_id, $label_type) {
        // 先从cache获取数据
        $cache_key = self::GetCustomerTypeCacheKey($customer_id, $label_type);
        $cached_result = \Cache::get($cache_key);
        if ($cached_result)
            return $cached_result;

        // 再从数据库获取，获取到了则种cache
        $db_result = parent::getInfoByCustomerIdType($customer_id, $label_type);
        if (!$db_result)
            return $db_result;
        \Cache::put($cache_key, $db_result, self::CACHE_EXPIRE);

        return $db_result;
    }
}
