<?php

namespace App\Http\Requests\Area;

use Illuminate\Foundation\Http\FormRequest;

class StateIndexRequest extends FormRequest
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
        ];
    }

}
