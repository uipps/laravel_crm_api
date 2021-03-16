<?php 

namespace App\ModelFilters;

use EloquentFilter\ModelFilter;
use Illuminate\Support\Arr;

class OrderFilter extends ModelFilter
{
    /**
    * Related Models that have ModelFilters as well as the method on the ModelFilter
    * As [relationMethod => [input_key1, input_key2]].
    *
    * @var array
    */
    //  public $relations = [
    //      'type_ order' => []
    //  ];

    protected $drop_id = false;

    public function orderNo($orderNo)
    {
        return $this->where('order_no', $orderNo);
    }

    public function customerName($name)
    {
        return $this->where('customer_name', 'like', '%'.$name.'%');
    }

    public function tel($tel)
    {
        return $this->where('tel', 'like', '%'.$tel.'%');
    }

    public function preOptType($type)
    {
        if($type == 10){
            return $this->where('audit_status', 0);
        }else{
            return $this->where('pre_opt_type', $type);
        }
    }

    public function orderTime($time)
    {
        $startTime = date('Y-m-d H:i:s', $time[0]);
        $endTime = date('Y-m-d H:i:s', $time[1]);

        return $this->whereBetween('order_time', [$startTime, $endTime]);
    }

    public function orderStatus($status)
    {
        return $this->where('order_status', $status);
    }

    // 过滤查询订单类型
    public function orderType($type)
    {
        $type = Arr::wrap($type);
        return $this->whereIn('order_type', $type);
    }

    public function orderSecondType($type)
    {
        return $this->where('order_second_type', $type);
    }

    public function shippingStatus($status)
    {
        $status = Arr::wrap($status);
        return $this->whereIn('shipping_status', $status);
    }

    public function orderScope($value)
    {
        return $this->where('order_scope', $value);
    }

    public function distributeStatus($value)
    {
        return $this->where('distribute_status', $value);
    }

}
