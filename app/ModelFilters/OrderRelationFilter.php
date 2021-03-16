<?php 

namespace App\ModelFilters;

use EloquentFilter\ModelFilter;
use Illuminate\Support\Arr;

class OrderRelationFilter extends ModelFilter
{
    /**
    * Related Models that have ModelFilters as well as the method on the ModelFilter
    * As [relationMethod => [input_key1, input_key2]].
    *
    * @var array
    */
     public $relations = [
         'order' => ['order_no', 'customer_name', 'tel', 'order_status', 'order_type', 'order_second_type', 'shipping_status', 'distribute_status']
     ];

    protected $drop_id = false;

    // 审核状态
    public function auditStatus($status)
    {
        return $this->where('audit_status', $status);
    }

    // 原订单号
    public function sourceOrderNo($orderNo)
    {
        return $this->where('source_order_no', $orderNo);
    }

    public function status($status)
    {
        return $this->where('status', $status);
    }

}
