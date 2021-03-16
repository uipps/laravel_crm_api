<?php

namespace App\Http\Requests\PreSaleOrder;

use App\Mappers\CommonMapper;
use App\Mappers\RouteMapper;
use Illuminate\Foundation\Http\FormRequest;

class UpdateRequest extends FormRequest
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
        $jobType = 1;
        $orderType = RouteMapper::getOrderType($jobType);
        // dd($orderType);
        if($orderType == 'audit') {
            return [
                'pre_opt_type' => 'required',
            ];
        }

        return [];
    }

    public function attributes()
    {
        return [

        ];
    }

    public function messages()
    {
        return [

        ];
    }
}
