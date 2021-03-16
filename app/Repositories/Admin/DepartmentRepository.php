<?php

namespace App\Repositories\Admin;

class DepartmentRepository extends DepartmentRepositoryImpl
{
    const CACHE_EXPIRE = 1;  // 单位秒，缓存时间
    const CACHE_EXPIRE_LIST = 1;

    private static function GetCacheKey($id) {
        return 'db:sys_department:detail-id-' . $id;
    }

    private static function GetListCacheKey() {
        return 'db:sys_department:list-all-';
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

    public function getAllDepartment() {
        // 先从cache获取数据
        $cache_key = self::GetListCacheKey();
        $cached_result = \Cache::get($cache_key);
        if ($cached_result)
            return $cached_result;

        // 再从数据库获取，获取到了则种cache
        $db_result = parent::getAllDepartment();
        if (!$db_result)
            return $db_result;
        \Cache::put($cache_key, $db_result, self::CACHE_EXPIRE_LIST);

        return $db_result;
    }

    // 直接删除全量缓存
    public function deleteAllListCache() {
        $cache_key = self::GetListCacheKey();
        \Cache::forget($cache_key); // 直接删除，以后有空了可以进行高并发下的更新操作
        return 1;
    }

    public function insertGetId($data_arr) {
        $insert_id = parent::insertGetId($data_arr);
        $cache_key = self::GetListCacheKey();
        \Cache::forget($cache_key); // 直接删除
        return $insert_id;
    }

    public function updateData($id, $data_arr) {
        parent::updateData($id, $data_arr);
        $cache_key = self::GetListCacheKey();
        \Cache::forget($cache_key); // 直接删除，列表

        $cache_key = self::GetCacheKey($id);
        \Cache::forget($cache_key); // 直接删除，单条
    }

}
