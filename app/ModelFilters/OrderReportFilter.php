<?php 

namespace App\ModelFilters;

use EloquentFilter\ModelFilter;
use Illuminate\Support\Arr;

class OrderReportFilter extends ModelFilter
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

    public function orderTimeStart($time)
    {
        $time = date('Y-m-d H:i:s', $time);
        return $this->where('order_time', '>', $time);
    }

    public function orderTimeEnd($time)
    {
        $time = date('Y-m-d H:i:s', $time);
        return $this->where('order_time', '<', $time);
    }

    public function preOptTimeStart($time)
    {
        $time = date('Y-m-d H:i:s', $time);
        return $this->where('pre_opt_time', '>', $time);
    }

    public function preOptTimeEnd($time)
    {
        $time = date('Y-m-d H:i:s', $time);
        return $this->where('pre_opt_time', '<', $time);
    }

    public function distributeTimeStart($time)
    {
        $time = date('Y-m-d H:i:s', $time);
        return $this->where('distribute_time', '>', $time);
    }

    public function distributeTimeEnd($time)
    {
        $time = date('Y-m-d H:i:s', $time);
        return $this->where('distribute_time', '<', $time);
    }

    public function reportPreOptType($type)
    {
        if($type == 10){
            return $this->where('audit_status', 0);
        }else{
            return $this->where('pre_opt_type', $type);
        }
    }

    // 过滤查询订单类型
    public function orderType($type)
    {
        $type = Arr::wrap($type);
        return $this->whereIn('order_type', $type);
    }

    public function shippingStatus($status)
    {
        $status = Arr::wrap($status);
        return $this->whereIn('shipping_status', $status);
    }

    public function countryId($value)
    {
        return $this->where('country_id', $value);
    }

    public function languageId($value)
    {
        return $this->where('language_id', $value);
    }


    public function callStatus($value)
    {
        if($value){
            return $this->where('call_num', '>', 0);
        }else{
            return $this->where('call_num', 0);
        }
    }

    public function departmentId($value)
    {
        if($value){
            return $this->where('department_id', $value);
        }
    }

    public function userId($value)
    {
        return $this->where('pre_sale_id', $value);
    }

    public function orderSendType($type)
    {
        return $this->where('order_second_type', $type);
    }

    public function auditTimeStart($time)
    {
        $time = date('Y-m-d H:i:s', $time);
        return $this->where('audit_time', '>', $time);
    }

    public function auditTimeEnd($time)
    {
        $time = date('Y-m-d H:i:s', $time);
        return $this->where('audit_time', '<', $time);
    }

    public function auditStatus($status)
    {
        return $this->where('audit_status', $status);
    }

}
