<?php

namespace App\Http\Requests\AfterSaleOrder;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class AuditRequest extends FormRequest
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
            'ids' => 'required',
            'audit_status' => 'required|in:0,1,-1'
        ];
    }

}
