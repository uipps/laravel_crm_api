<?php

namespace App\Http\Resources;

use App\Mappers\CommonMappper;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Arr;

class OrderReportResource extends JsonResource
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
        $model->append(['order_type_name']);

        $this->makeHidden();

        $relArr = $this->relationsToArray();
        $ret = $model->attributesToArray();
        
        $ret['goods_info'] =  Arr::get($relArr, 'goods_info');
        $ret['country_name'] = Arr::get($relArr, 'country.display_name');
        $ret['language_name'] = Arr::get($relArr, 'language.display_name');
        $ret['pre_sale_name'] = Arr::get($relArr, 'pre_sale.real_name', '-');
        $ret['after_sale_name'] = Arr::get($relArr, 'after_sale.real_name', '-');
        $ret['department_name'] = Arr::get($relArr, 'department.name', '-');


        return $ret;
    }
}
