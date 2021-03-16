<?php

namespace App\Http\Resources;

use App\Mappers\CommonMappper;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Arr;

class OrderResource extends JsonResource
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

        // $this->statusObject('order', $model->order_status);
        $this->makeHidden();

        $relArr = $this->relationsToArray();

        // id要以主表order为主
        $typeOrderArr = Arr::get($relArr, 'after_sale');
        unset($typeOrderArr['id']);
        
        $ret = array_merge($model->attributesToArray(), $typeOrderArr);
        $ret['goods_info'] = Arr::get($relArr,'order_detail');

        return $ret;
    }
}
