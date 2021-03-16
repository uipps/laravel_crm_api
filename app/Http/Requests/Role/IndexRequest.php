<?php

namespace App\Http\Requests\Role;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class IndexRequest extends FormRequest
{
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
            'page_size' => 'sometimes|integer|between:5,50',
        ];
    }

    public function attributes()
    {
        return [
            'page_size' => '每页查询数',
        ];
    }

    public function messages()
    {
        return [
            'integer' => ':attribute 数据格式必须是整数',
            'between' => ':attribute 必须大于等于 :min 且小于等于 :max',
        ];
    }

    public function withValidator(Validator $validator)
    {
        $pageSize = $this->input('page_size', 20);
        $this->merge(['page_size' => $pageSize]);
    }
}
