<?php

namespace App\Repositories\Admin;

class DepartmentWeightRepository extends DepartmentWeightRepositoryImpl
{
    const CACHE_EXPIRE = 1;  // 单位秒，缓存时间

    private static function GetCacheKey($id) {
        return 'db:sys_department_weight:detail-id-' . $id;
    }

    private static function GetDeptCacheKey($dept_id) {
        return 'db:sys_department_weight:weight-list-department-id-' . $dept_id;
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

    public function setDeptOrderRate($param) {
        if (!parent::setDeptOrderRate($param)) return false;
        // 更新后，记得删除每个id对应的cache
        foreach ($param as $row) {
            $cache_key = self::GetCacheKey($row['id']);
            \Cache::forget($cache_key);
        }
        return true;
    }

    public function getDeptWeightByDeptId($dept_id) {
        // 先从cache获取数据
        $cache_key = self::GetDeptCacheKey($dept_id);
        $cached_result = \Cache::get($cache_key);
        if ($cached_result)
            return $cached_result;

        // 再从数据库获取，获取到了则种cache
        $db_result = parent::getDeptWeightByDeptId($dept_id);
        if (!$db_result)
            return $db_result;
        \Cache::put($cache_key, $db_result, self::CACHE_EXPIRE);

        return $db_result;
    }
}
