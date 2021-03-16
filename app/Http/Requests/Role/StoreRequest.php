<?php

namespace App\Http\Requests\Role;

use App\Models\Admin\SysPrivilege;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use App\Models\Role;

class StoreRequest extends FormRequest
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
            'name' => 'required|string|max:16',
            'remark' => 'sometimes|string|max:300',
        ];
    }

    public function attributes()
    {
        return [
            'name' => '角色名称',
            'remark' => '备注',
        ];
    }

    public function messages()
    {
        return [
            'required' => ':attribute 不能为空',
            'string' => ':attribute 数据类型必须为字符串',
            'max' => ':attribute 不能超过 :max 个字符',
        ];
    }

    public function withValidator(Validator $validator)
    {
        $validator->after(function ($validator) {
            $this->checkPrivilegeIds($validator);
        });
    }

    /**
     * 检查参数 privilegeIds 是否有效
     */
    private function checkPrivilegeIds(Validator $validator)
    {
        if (!$this->has('privilege_ids')) {
            return;
        }

        $privilegeIds = $this->privilege_ids;

        // 如果是 1,2,3 这样的格式则转化为数组
        if (is_string($privilegeIds)) {
            $privilegeIds = explode(',', $privilegeIds);
        }
        if (!is_array($privilegeIds)) {
            $validator->errors()->add('privilege_ids', '角色权限 的数据格式必须是数组或以英文半角逗号分隔的字符串');
            return;
        }
        if (empty($privilegeIds)) {
            return;
        }

        // 过滤有效的权限 ID
        $ids = array_filter($privilegeIds, function ($v) {
            return is_numeric($v);
        });
        $ids = SysPrivilege::select('id')->whereIn('id', $ids)
            ->pluck('id')->toArray();
        // 过滤结果替代请求中的值
        $this->merge(['privilege_ids' => $ids]);

        // 将非法的数据输出错误
        foreach ($privilegeIds as $k => $v) {
            if (is_numeric($v) && in_array($v, $ids)) {
                continue;
            }
            $validator->errors()->add('privilege_ids', "角色权限[{$k}] 数据格式非法或指代的权限无效");
        }
    }
}
