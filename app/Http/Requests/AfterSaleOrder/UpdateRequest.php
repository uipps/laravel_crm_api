<?php

namespace App\Http\Requests\AfterSaleOrder;

use App\Mappers\CommonMapper;
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
