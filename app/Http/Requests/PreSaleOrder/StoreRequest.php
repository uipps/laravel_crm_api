<?php

namespace App\Http\Requests\PreSaleOrder;

use Illuminate\Foundation\Http\FormRequest;
// 只有创建手工单的时候
class StoreRequest extends FormRequest
{
    use HasInitRequest;

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'customer_name' => 'required|string|between:3,60',
            'tel' => 'required',
            'language_id' => 'required',
            'country_id' => 'required',
            'zone_prov_name' => 'required',
            'zone_city_name' => 'required',
            'zip_code' => 'required',
            'goods_info' => 'required',
        ];
    }

    public function attributes()
    {
        return [
            'customer_name' => __('field.customer_name'),
            'tel' => __('field.tel'),
            'language_id' => __('field.language_id'),
            'country_id' => __('field.country_id'),
            'zone_prov_name' => __('field.zone_prov_name'),
            'zone_city_name' => __('field.zone_city_name'),
            'goods_info' => __('field.goods_info'),
        ];
    }

    // public function messages()
    // {
    //     return [

    //     ];
    // }

}
