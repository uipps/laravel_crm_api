<?php 

namespace App\ModelFilters;

use EloquentFilter\ModelFilter;
use Illuminate\Support\Arr;

class CustomerFilter extends ModelFilter
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

    public function customerNameId($value)
    {
        if(is_numeric($value)){
            return $this->where('id', $value);

        }else{
            return $this->where('customer_name', 'like', '%'.$value.'%');

        }
    }

    public function qualityLevel($level)
    {
        return $this->where('quality_level', $level);
    }

    public function tel($tel)
    {
        return $this->where('tel', $tel);
    }


    public function receivedFlag($flag)
    {
        return $this->where('received_flag', $flag);
    }
}
