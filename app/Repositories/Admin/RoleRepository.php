<?php

namespace App\Repositories\Admin;

class RoleRepository extends RoleRepositoryImpl
{
    const CACHE_EXPIRE = 1;  // 单位秒，缓存时间
    const CACHE_EXPIRE_ROLE_USERS_NUM = 1;
    const CACHE_EXPIRE_LIST = 1;

    private static function GetCacheKey($id) {
        return 'db:role:detail-id-' . $id;
    }

    private static function GetRoleUsersNumCacheKey($id) {
        return 'db:role:role-' . $id . '-users-num';
    }

    private static function GetListCacheKey() {
        return 'db:role:list-all-';
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

    // 获取role-id关联的有效用户数
    public function getRoleValidUserNum($id) {
        // 先从cache获取数据
        $cache_key = self::GetRoleUsersNumCacheKey($id);
        $cached_result = \Cache::get($cache_key);
        if ($cached_result)
            return $cached_result;

        // 再从数据库获取，获取到了则种cache
        $db_result = parent::getRoleValidUserNum($id);
        if (!$db_result)
            return $db_result;
        \Cache::put($cache_key, $db_result, self::CACHE_EXPIRE_ROLE_USERS_NUM);

        return $db_result;
    }

    // 更新用户数缓存，简单处理直接删除缓存
    public function updateUserNumCache($data_arr) {
        if (!isset($data_arr['role_id']) || !$data_arr['role_id']) {
            return 1;
        }
        $cache_key = self::GetRoleUsersNumCacheKey($data_arr['role_id']);
        \Cache::forget($cache_key); // 直接删除，以后有空了可以进行高并发下的更新操作
        return 1;
    }

    public function getAllRole() {
        // 先从cache获取数据
        $cache_key = self::GetListCacheKey();
        $cached_result = \Cache::get($cache_key);
        if ($cached_result)
            return $cached_result;

        // 再从数据库获取，获取到了则种cache
        $db_result = parent::getAllRole();
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

}
