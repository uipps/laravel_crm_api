<?php

namespace App\Repositories\Admin;

class CountryRepository extends CountryRepositoryImpl
{
    const CACHE_EXPIRE = 1;  // 单位秒，缓存时间
    const CACHE_EXPIRE_LIST = 1;

    private static function GetCacheKey($id) {
        return 'db:sys_country:detail-id-' . $id;
    }

    private static function GetListCacheKey() {
        return 'db:sys_country:list-all-';
    }

    private static function GetPageListCacheKey() {
        return 'db:sys_country:page-list-all-';
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

    public function getAllCountry() {
        // 先从cache获取数据
        $cache_key = self::GetListCacheKey();
        $cached_result = \Cache::get($cache_key);
        if ($cached_result)
            return $cached_result;

        // 再从数据库获取，获取到了则种cache
        $db_result = parent::getAllCountry();
        if (!$db_result)
            return $db_result;
        \Cache::put($cache_key, $db_result, self::CACHE_EXPIRE_LIST);

        return $db_result;
    }

    public function getList($params, $field = ['*']) {
        if ((isset($params['page']) && $params['page'] > 1) || (isset($params['limit']) && parent::PAGE_SIZE != $params['limit']))
            return parent::getList($params, $field);

        // 当前数量不多，因此直接加缓存
        // 先从cache获取数据
        $cache_key = self::GetPageListCacheKey();
        $cached_result = \Cache::get($cache_key);
        if ($cached_result)
            return $cached_result;

        // 再从数据库获取，获取到了则种cache
        $db_result = parent::getList($params, $field);
        if (!$db_result)
            return $db_result;
        \Cache::put($cache_key, $db_result, self::CACHE_EXPIRE_LIST);

        return $db_result;
    }
}
