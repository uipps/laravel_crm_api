<?php

namespace App\Repositories\OrderPreSale;

class OrderRepository extends OrderRepositoryImpl
{
    const CACHE_EXPIRE = 1;  // 单位秒，缓存时间
    const CACHE_EXPIRE_ORDER_STATS = 300;

    private static function GetCacheKey($id) {
        return 'db:order:detail-id-' . $id;
    }

    private static function GetCountOrderTotalAdsCacheKey($uid) {
        return 'db:order:order-stats:order-total-ads-user-id-' . $uid;
    }

    private static function GetCountAuditNoCacheKey($uid) {
        return 'db:order:order-stats:audit-no-user-id-' . $uid;
    }

    private static function GetCountAuditYesCacheKey($uid) {
        return 'db:order:order-stats:audit-yes-user-id-' . $uid;
    }

    private static function GetCountDistributeNoCacheKey($uid) {
        return 'db:order:order-stats:distribute-no-user-id-' . $uid;
    }

    private static function GetCountDistributeYesCacheKey($uid) {
        return 'db:order:order-stats:distribute-yes-user-id-' . $uid;
    }

    private static function GetCountManualOrderCacheKey($uid) {
        return 'db:order:order-stats:manual-order-user-id-' . $uid;
    }

    private static function GetCountRepeatOrderCacheKey($uid) {
        return 'db:order:order-stats:repeat-order-user-id-' . $uid;
    }

    private static function GetCountInvalidOrderCacheKey($uid) {
        return 'db:order:order-stats:invalid-order-user-id-' . $uid;
    }

    private static function GetCountAbnormalNoCacheKey($uid) {
        return 'db:order:order-stats:abnormal-no-user-id-' . $uid;
    }

    private static function GetCountAbnormalYesCacheKey($uid) {
        return 'db:order:order-stats:abnormal-yes-user-id-' . $uid;
    }

    private static function GetCountAskforcancelNoDealwithCacheKey($uid) {
        return 'db:order:order-stats:askforcancel_no_dealwith-user-id-' . $uid;
    }

    private static function GetCountAskforcancelSuccCacheKey($uid) {
        return 'db:order:order-stats:askforcancel_succ-user-id-' . $uid;
    }

    private static function GetCountAskforcancelFailCacheKey($uid) {
        return 'db:order:order-stats:askforcancel_fail-user-id-' . $uid;
    }

    private static function GetCountAskforcancelFinishCacheKey($uid) {
        return 'db:order:order-stats:askforcancel_finish-user-id-' . $uid;
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

    // 订单数，统计数据，依据用户进行缓存
    //     level 岗位 1管理员,2员工
    public function getOrderNumStatsByUserId($uid, $user_info, $child_dept_list=[]) {
        // 统计各种订单数据
        $data_arr = [
            'order_num_total' => 0,        // 订单数, 通过下面4项之和：
            'audit_no' => 0,               // 未审核
            'audit_yes' => 0,              // 已审核
            'distribute_no' => 0,          // 未分配
            'distribute_yes' => 0,         // 已分配

            'manul_order_num' => 0,        // 手动下单数
            'repeat_order_num' => 0,       // 重复订单数
            'invalid_order_num' => 0,      // 无效订单数
            'abnormal_order_num' => 0,     // 异常订单数，下面2项之和：
            'abnormal_no_dealwith' => 0,      // 未处理异常订单数
            'abnormal_dealwith' => 0,         // 已处理异常订单数

            'askforcancel_total' => 0,     // 取消订单申请，下面3项之和（不包括归档）：
            'askforcancel_no_dealwith' => 0,// 取消订单申请 - 待处理
            'askforcancel_succ' => 0,       // 取消订单申请 - 取消成功
            'askforcancel_fail' => 0,       // 取消订单申请 - 取消失败
            'askforcancel_finish' => 0,     // 取消订单申请 - 归档
        ];

        // 查询未审核订单数据，主管查看下属部门的所有员工总数据，员工则只能查看自己对应数据
        $data_arr['audit_no'] = self::sqlCountAuditNo($uid, $user_info, $child_dept_list);
        $data_arr['audit_yes'] = self::sqlCountAuditYes($uid, $user_info, $child_dept_list);
        $data_arr['distribute_no'] = self::sqlCountDistributeNo($uid, $user_info, $child_dept_list);
        $data_arr['distribute_yes'] = self::sqlCountDistributeYes($uid, $user_info, $child_dept_list);

        $data_arr['manul_order_num'] = self::sqlCountManualOrder($uid, $user_info, $child_dept_list); // manul/manual 两个单词都对，均表示手动
        $data_arr['repeat_order_num'] = self::sqlCountRepeatOrder($uid, $user_info, $child_dept_list);
        $data_arr['invalid_order_num'] = self::sqlCountInvalidOrder($uid, $user_info, $child_dept_list);

        $data_arr['abnormal_no_dealwith'] = self::sqlCountAbnormalNo($uid, $user_info, $child_dept_list);
        $data_arr['abnormal_dealwith'] = self::sqlCountAbnormalYes($uid, $user_info, $child_dept_list);

        $data_arr['askforcancel_no_dealwith'] = self::sqlCountAskforcancelNoDealwith($uid, $user_info, $child_dept_list);
        $data_arr['askforcancel_succ'] = self::sqlCountAskforcancelSucc($uid, $user_info, $child_dept_list);
        $data_arr['askforcancel_fail'] = self::sqlCountAskforcancelFail($uid, $user_info, $child_dept_list);
        $data_arr['askforcancel_finish'] = self::sqlCountAskforcancelFinish($uid, $user_info, $child_dept_list);

        // 广告单总数，也许并不是4者相加之和
        $data_arr['order_num_total'] = self::sqlCountOrderTotalAds($uid, $user_info, $child_dept_list);

        // 总数计算
        //$data_arr['order_num_total'] = $data_arr['audit_no'] + $data_arr['audit_yes'] + $data_arr['distribute_no'] + $data_arr['distribute_yes'];
        $data_arr['askforcancel_total'] = $data_arr['askforcancel_no_dealwith'] + $data_arr['askforcancel_succ'] + $data_arr['askforcancel_fail'];
        $data_arr['abnormal_order_num'] = $data_arr['abnormal_no_dealwith'] + $data_arr['abnormal_dealwith'];

        return $data_arr;
    }

    // 广告单总数
    public function sqlCountOrderTotalAds($uid, $login_user_info, $child_dept_list=[]) {
        // 先从cache获取数据
        $cache_key = self::GetCountOrderTotalAdsCacheKey($uid);
        $cached_result = \Cache::get($cache_key);
        if (is_numeric($cached_result))
            return $cached_result;

        // 再从数据库获取，获取到了则种cache
        $db_result = parent::sqlCountOrderTotalAds($uid, $login_user_info, $child_dept_list);
        if (!is_numeric($db_result))
            return 0;
        \Cache::put($cache_key, $db_result, self::CACHE_EXPIRE_ORDER_STATS);

        return $db_result;
    }

    // 获取"未审核"订单数, 数字0也存放到缓存
    public function sqlCountAuditNo($uid, $login_user_info, $child_dept_list=[]) {
        // 先从cache获取数据
        $cache_key = self::GetCountAuditNoCacheKey($uid);
        $cached_result = \Cache::get($cache_key);
        if (is_numeric($cached_result))
            return $cached_result;

        // 再从数据库获取，获取到了则种cache
        $db_result = parent::sqlCountAuditNo($uid, $login_user_info, $child_dept_list);
        if (!is_numeric($db_result))
            return 0;
        \Cache::put($cache_key, $db_result, self::CACHE_EXPIRE_ORDER_STATS);

        return $db_result;
    }

    // 数字0也存放到缓存
    public function sqlCountAuditYes($uid, $login_user_info, $child_dept_list=[]) {
        // 先从cache获取数据
        $cache_key = self::GetCountAuditYesCacheKey($uid);
        $cached_result = \Cache::get($cache_key);   // var_dump($cached_result); null
        if (is_numeric($cached_result))
            return $cached_result;

        // 再从数据库获取，获取到了则种cache
        $db_result = parent::sqlCountAuditYes($uid, $login_user_info, $child_dept_list);
        if (!is_numeric($db_result))
            return 0;
        \Cache::put($cache_key, $db_result, self::CACHE_EXPIRE_ORDER_STATS);

        return $db_result;
    }

    // 获取"未分配"订单数, 数字0也存放到缓存
    public function sqlCountDistributeNo($uid, $login_user_info, $child_dept_list=[]) {
        // 先从cache获取数据
        $cache_key = self::GetCountDistributeNoCacheKey($uid);
        $cached_result = \Cache::get($cache_key);
        if (is_numeric($cached_result))
            return $cached_result;

        // 再从数据库获取，获取到了则种cache
        $db_result = parent::sqlCountDistributeNo($uid, $login_user_info, $child_dept_list);
        if (!is_numeric($db_result))
            return 0;
        \Cache::put($cache_key, $db_result, self::CACHE_EXPIRE_ORDER_STATS);

        return $db_result;
    }

    // 获取"已分配"订单数, 数字0也存放到缓存
    public function sqlCountDistributeYes($uid, $login_user_info, $child_dept_list=[]) {
        // 先从cache获取数据
        $cache_key = self::GetCountDistributeYesCacheKey($uid);
        $cached_result = \Cache::get($cache_key);
        if (is_numeric($cached_result))
            return $cached_result;

        // 再从数据库获取，获取到了则种cache
        $db_result = parent::sqlCountDistributeYes($uid, $login_user_info, $child_dept_list);
        if (!is_numeric($db_result))
            return 0;
        \Cache::put($cache_key, $db_result, self::CACHE_EXPIRE_ORDER_STATS);

        return $db_result;
    }

    // 手工, 数字0也存放到缓存
    public function sqlCountManualOrder($uid, $login_user_info, $child_dept_list=[]) {
        // 先从cache获取数据
        $cache_key = self::GetCountManualOrderCacheKey($uid);
        $cached_result = \Cache::get($cache_key);
        if (is_numeric($cached_result))
            return $cached_result;

        // 再从数据库获取，获取到了则种cache
        $db_result = parent::sqlCountManualOrder($uid, $login_user_info, $child_dept_list);
        if (!is_numeric($db_result))
            return 0;
        \Cache::put($cache_key, $db_result, self::CACHE_EXPIRE_ORDER_STATS);

        return $db_result;
    }

    // 获取"重复"订单数, 数字0也存放到缓存
    public function sqlCountRepeatOrder($uid, $login_user_info, $child_dept_list=[]) {
        // 先从cache获取数据
        $cache_key = self::GetCountRepeatOrderCacheKey($uid);
        $cached_result = \Cache::get($cache_key);
        if (is_numeric($cached_result))
            return $cached_result;

        // 再从数据库获取，获取到了则种cache
        $db_result = parent::sqlCountRepeatOrder($uid, $login_user_info, $child_dept_list);
        if (!is_numeric($db_result))
            return 0;
        \Cache::put($cache_key, $db_result, self::CACHE_EXPIRE_ORDER_STATS);

        return $db_result;
    }

    // 获取"无效"订单数, 数字0也存放到缓存
    public function sqlCountInvalidOrder($uid, $login_user_info, $child_dept_list=[]) {
        // 先从cache获取数据
        $cache_key = self::GetCountInvalidOrderCacheKey($uid);
        $cached_result = \Cache::get($cache_key);
        if (is_numeric($cached_result))
            return $cached_result;

        // 再从数据库获取，获取到了则种cache
        $db_result = parent::sqlCountInvalidOrder($uid, $login_user_info, $child_dept_list);
        if (!is_numeric($db_result))
            return 0;
        \Cache::put($cache_key, $db_result, self::CACHE_EXPIRE_ORDER_STATS);

        return $db_result;
    }

    // 获取"异常-未处理"订单数, 数字0也存放到缓存
    public function sqlCountAbnormalNo($uid, $login_user_info, $child_dept_list=[]) {
        // 先从cache获取数据
        $cache_key = self::GetCountAbnormalNoCacheKey($uid);
        $cached_result = \Cache::get($cache_key);
        if (is_numeric($cached_result))
            return $cached_result;

        // 再从数据库获取，获取到了则种cache
        $db_result = parent::sqlCountAbnormalNo($uid, $login_user_info, $child_dept_list);
        if (!is_numeric($db_result))
            return 0;
        \Cache::put($cache_key, $db_result, self::CACHE_EXPIRE_ORDER_STATS);

        return $db_result;
    }

    // 获取"异常-已处理"订单数, 数字0也存放到缓存
    public function sqlCountAbnormalYes($uid, $login_user_info, $child_dept_list=[]) {
        // 先从cache获取数据
        $cache_key = self::GetCountAbnormalYesCacheKey($uid);
        $cached_result = \Cache::get($cache_key);
        if (is_numeric($cached_result))
            return $cached_result;

        // 再从数据库获取，获取到了则种cache
        $db_result = parent::sqlCountAbnormalYes($uid, $login_user_info, $child_dept_list);
        if (!is_numeric($db_result))
            return 0;
        \Cache::put($cache_key, $db_result, self::CACHE_EXPIRE_ORDER_STATS);

        return $db_result;
    }

    // 获取"取消申请-未处理"订单数, 数字0也存放到缓存
    public function sqlCountAskforcancelNoDealwith($uid, $login_user_info, $child_dept_list=[]) {
        // 先从cache获取数据
        $cache_key = self::GetCountAskforcancelNoDealwithCacheKey($uid);
        $cached_result = \Cache::get($cache_key);
        if (is_numeric($cached_result))
            return $cached_result;

        // 再从数据库获取，获取到了则种cache
        $db_result = parent::sqlCountAskforcancelNoDealwith($uid, $login_user_info, $child_dept_list);
        if (!is_numeric($db_result))
            return 0;
        \Cache::put($cache_key, $db_result, self::CACHE_EXPIRE_ORDER_STATS);

        return $db_result;
    }

    // 获取"取消申请-成功"订单数, 数字0也存放到缓存
    public function sqlCountAskforcancelSucc($uid, $login_user_info, $child_dept_list=[]) {
        // 先从cache获取数据
        $cache_key = self::GetCountAskforcancelSuccCacheKey($uid);
        $cached_result = \Cache::get($cache_key);
        if (is_numeric($cached_result))
            return $cached_result;

        // 再从数据库获取，获取到了则种cache
        $db_result = parent::sqlCountAskforcancelSucc($uid, $login_user_info, $child_dept_list);
        if (!is_numeric($db_result))
            return 0;
        \Cache::put($cache_key, $db_result, self::CACHE_EXPIRE_ORDER_STATS);

        return $db_result;
    }

    // 获取"取消申请-失败"订单数, 数字0也存放到缓存
    public function sqlCountAskforcancelFail($uid, $login_user_info, $child_dept_list=[]) {
        // 先从cache获取数据
        $cache_key = self::GetCountAskforcancelFailCacheKey($uid);
        $cached_result = \Cache::get($cache_key);
        if (is_numeric($cached_result))
            return $cached_result;

        // 再从数据库获取，获取到了则种cache
        $db_result = parent::sqlCountAskforcancelFail($uid, $login_user_info, $child_dept_list);
        if (!is_numeric($db_result))
            return 0;
        \Cache::put($cache_key, $db_result, self::CACHE_EXPIRE_ORDER_STATS);

        return $db_result;
    }

    // 获取"取消申请-归档"订单数, 数字0也存放到缓存
    public function sqlCountAskforcancelFinish($uid, $login_user_info, $child_dept_list=[]) {
        // 先从cache获取数据
        $cache_key = self::GetCountAskforcancelFinishCacheKey($uid);
        $cached_result = \Cache::get($cache_key);
        if (is_numeric($cached_result))
            return $cached_result;

        // 再从数据库获取，获取到了则种cache
        $db_result = parent::sqlCountAskforcancelFinish($uid, $login_user_info, $child_dept_list);
        if (!is_numeric($db_result))
            return 0;
        \Cache::put($cache_key, $db_result, self::CACHE_EXPIRE_ORDER_STATS);

        return $db_result;
    }

    // 删除缓存
    public function deleteCacheByUidAndType($uid, $type = '') {
        if (!$uid) return 1;

        if ('audit' == $type) {
            $cache_key = self::GetCountAuditYesCacheKey($uid);
            \Cache::forget($cache_key);
            $cache_key = self::GetCountAuditNoCacheKey($uid);
            \Cache::forget($cache_key);
        }
        if ('distribute' == $type) {
            $cache_key = self::GetCountDistributeNoCacheKey($uid);
            \Cache::forget($cache_key);
            $cache_key = self::GetCountDistributeYesCacheKey($uid);
            \Cache::forget($cache_key);
        }
        if ('manual' == $type) {
            $cache_key = self::GetCountManualOrderCacheKey($uid);
            \Cache::forget($cache_key);
        }
        if ('askforcancel' == $type) {
            $cache_key = self::GetCountAskforcancelNoDealwithCacheKey($uid);
            \Cache::forget($cache_key);
            $cache_key = self::GetCountAskforcancelFailCacheKey($uid);
            \Cache::forget($cache_key);
            $cache_key = self::GetCountAskforcancelSuccCacheKey($uid);
            \Cache::forget($cache_key);
            $cache_key = self::GetCountAskforcancelFinishCacheKey($uid);
            \Cache::forget($cache_key);
        }
         // 直接删除，以后有空了可以进行高并发下的更新操作
        return 1;
    }

}
