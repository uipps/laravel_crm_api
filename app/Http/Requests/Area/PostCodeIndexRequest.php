<?php

namespace App\Http\Requests\Area;

use Illuminate\Foundation\Http\FormRequest;

class PostCodeIndexRequest extends FormRequest
{
    use AsIndexRequest;
    

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'country_id' => 'required|integer',
            'state_name' => 'required|string',
            'city_name' => 'required|string',
            'district_name' => 'required|string',
        ];
    }

}
