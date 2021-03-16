<?php

namespace App\Dto;

use ArrayAccess;

class BaseDto implements ArrayAccess
{
    const SUCCESS_CODE = 0;

    const DTO_FIELD_TOTOAL = 'total'; // count
    const DTO_FIELD_LIST   = 'list';  // datas
    const DTO_FIELD_PAGE   = 'page';
    const DTO_FIELD_OFFSET = 'offset';
    const DTO_FIELD_LIMIT  = 'limit';

    const WITHOUT_ORDER_STATS = 'without_order_stat'; // 是否携带订单统计数据
    const DTO_FIELD_ORDER_STATS = 'order_stats'; // 订单统计数据名
    const DTO_FIELD_NUMBER_STATS = 'number_stats'; // 统计数

    public function Assign($items) {
        if (!$items) return;
        if (is_object($items)) $items = collect($items)->toArray();
        foreach ($items as $property => $value) {
            if (null !== $value && property_exists($this, $property))
                $this->$property = $value;
        }
    }

    /**
     * @see ArrayAccess::offsetExists
     * @param int $offset
     */
    public function offsetExists($offset)
    {
        return isset($offset);
    }

    /**
     * @see ArrayAccess::offsetGet
     * @param int $offset
     */
    public function offsetGet($offset)
    {
        return $offset;
    }

    /**
     * @see ArrayAccess::offsetSet
     * @param int $offset
     */
    public function offsetSet($offset, $value)
    {
        throw new \Exception(__CLASS__ . ' is read only');
    }

    /**
     * @see ArrayAccess::offsetUnset
     * @param int $offset
     */
    public function offsetUnset($offset)
    {
        throw new \Exception(__CLASS__ . ' is read only');
    }

}
