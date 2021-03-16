<?php

namespace App\Http\Resources;

use App\Mappers\CommonMappper;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Arr;

class OrderRelationResource extends JsonResource
{
    use BaseResource;
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        /**
         * @var  \Illuminate\Database\Eloquent\Model $model
         */
        $model = $this->getModel();

        $this->makeHidden();

        $relArr = $this->relationsToArray();
        $curArr = $model->attributesToArray();
        $orderArr = (array) Arr::get($relArr, 'order');
        unset($relArr['order']);

        //返回是数组，就取第一个对象
        $after_sale_id = Arr::get($relArr, 'after_sale.0.id');
        if($after_sale_id){
            $relArr['after_sale'] = Arr::get($relArr, 'after_sale.0');
        }

        $relArr['order_audit_id'] = Arr::get($relArr, 'order_audit.id');
        unset($relArr['order_audit']);

        $relArr['order_distribute_id'] = Arr::get($relArr, 'order_distribute.id');
        unset($relArr['order_distribute']);
        
        $ret = array_merge($orderArr, $curArr, $relArr);


        return $ret;
    }
}
