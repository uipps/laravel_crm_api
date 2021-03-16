<?php

namespace App\Repositories\Admin;

class PromotionsGoodsRepository extends PromotionsGoodsRepositoryImpl
{
    const CACHE_EXPIRE = 1;  // 单位秒，缓存时间
    const CACHE_EXPIRE_LIST = 1;

    private static function GetCacheKey($id) {
        return 'db:promotions_goods:detail-id-' . $id;
    }

    private static function GetListCacheKey($status) {
        return 'db:promotions_goods:goods-list-' . $status;
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

    public function getAllPromotionsGoodsSku($status='all') {
        // 先从cache获取数据
        $cache_key = self::GetListCacheKey($status);
        $cached_result = \Cache::get($cache_key);
        if ($cached_result)
            return $cached_result;

        // 再从数据库获取，获取到了则种cache
        $db_result = parent::getAllPromotionsGoodsSku($status);
        if (!$db_result)
            return $db_result;
        \Cache::put($cache_key, $db_result, self::CACHE_EXPIRE_LIST);

        return $db_result;
    }

}
