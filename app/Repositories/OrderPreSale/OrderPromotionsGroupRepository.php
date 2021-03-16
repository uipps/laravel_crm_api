<?php

namespace App\Repositories\OrderPreSale;

class OrderPromotionsGroupRepository extends OrderPromotionsGroupRepositoryImpl
{
    const CACHE_EXPIRE = 1;  // 单位秒，缓存时间
    const CACHE_EXPIRE_LIST = 1;


    private static function GetCacheKey($id) {
        return 'db:order_promotions_group:detail-id-' . $id;
    }

    private static function GetListCacheKey($type=1) {
        return 'db:order_promotions_group:list-type-' . $type;
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

    // 通常取值1，返回启用状态的即可
    public function getAllPromotionsGroup($type=1) {
        // 先从cache获取数据
        $cache_key = self::GetListCacheKey($type);
        $cached_result = \Cache::get($cache_key);
        if ($cached_result)
            return $cached_result;

        // 再从数据库获取，获取到了则种cache
        $db_result = parent::getAllPromotionsGroup($type);
        if (!$db_result)
            return $db_result;
        \Cache::put($cache_key, $db_result, self::CACHE_EXPIRE_LIST);

        return $db_result;
    }

    // 插入新数据，删除列表缓存
    public function insertMultiByRuleStrs($rule_strs, $create_time, $creator_id) {
        parent::insertMultiByRuleStrs($rule_strs, $create_time, $creator_id);
        $cache_key = self::GetListCacheKey(1);
        \Cache::forget($cache_key);
    }
}
