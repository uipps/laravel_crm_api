<?php

namespace App\Repositories\Admin;

class PromotionsGoodsNumRuleRepository extends PromotionsGoodsNumRuleRepositoryImpl
{
    const CACHE_EXPIRE = 1;  // 单位秒，缓存时间
    const CACHE_EXPIRE_LIST = 1;

    private static function GetCacheKey($id) {
        return 'db:promotions_goods_num_rule:detail-id-' . $id;
    }

    private static function GetListCacheKey($status) {
        return 'db:promotions_goods_num_rule:rule-list-'. $status;
    }

    private static function GetListWithPromotionCacheKey() {
        return 'db:promotions_goods_num_rule:rule-list-with-promotion';
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

    public function getAllPromotionsRules($status='') {
        // 先从cache获取数据
        $cache_key = self::GetListCacheKey($status);
        $cached_result = \Cache::get($cache_key);
        if ($cached_result)
            return $cached_result;

        // 再从数据库获取，获取到了则种cache
        $db_result = parent::getAllPromotionsRules($status);
        if (!$db_result)
            return $db_result;
        \Cache::put($cache_key, $db_result, self::CACHE_EXPIRE);

        return $db_result;
    }

    // 获取所有有效的活动规则，附带活动信息, 组成2维数组
    public function getAllPromotionsRulesWithPromotions() {
        // 先从cache获取数据
        $cache_key = self::GetListWithPromotionCacheKey();
        $cached_result = \Cache::get($cache_key);
        if ($cached_result)
            return $cached_result;

        // 再从数据库获取，获取到了则种cache
        $all_rules = self::getAllPromotionsRules(1);
        if (!$all_rules)
            return $all_rules;
        $all_promotions = (new PromotionsRepository())->getAllPromotions(1); // TODO 同级别不能掉用，有时间再优化一下
        //$l_goods_all = (new PromotionsGoodsRepository())->getAllPromotionsGoodsSku();  // 数据量大的话，只获取指定活动id的商品
        foreach ($all_rules as $key => $row) {
            // 活动数据merge进来，如果找不到，则需要删除节点
            if (!isset($all_promotions[$row['promotion_id']])) {
                unset($all_rules[$key]);
                continue;
            }
            $promotion_info = $all_promotions[$row['promotion_id']];
            unset($promotion_info['id']);
            unset($promotion_info['status']);
            unset($promotion_info['creator_id']);
            unset($promotion_info['created_time']);
            unset($promotion_info['updator_id']);
            unset($promotion_info['updated_time']);
            unset($promotion_info['deleted_time']);
            $row = array_merge($row, $promotion_info); // 合并字段

            // 如果活动的范围是单个商品
            //if (2 == $promotion_info['goods_scope'] && isset($l_goods_all[$row['promotion_id']]) && $l_goods_all[$row['promotion_id']]) {
            //    $row['promotion_goods'] = $l_goods_all[$row['promotion_id']];
            //}
            $all_rules[$key] = $row;
        }
        \Cache::put($cache_key, $all_rules, self::CACHE_EXPIRE_LIST);

        return $all_rules;
    }

}
