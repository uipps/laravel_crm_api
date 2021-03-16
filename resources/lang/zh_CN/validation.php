<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Validation Language Lines
    |--------------------------------------------------------------------------
    |
    | The following language lines contain the default error messages used by
    | the validator class. Some of these rules have multiple versions such
    | as the size rules. Feel free to tweak each of these messages here.
    |
    */

    'accepted' => 'The :attribute must be accepted.',
    'active_url' => 'The :attribute is not a valid URL.',
    'after' => 'The :attribute must be a date after :date.',
    'after_or_equal' => 'The :attribute must be a date after or equal to :date.',
    'alpha' => 'The :attribute may only contain letters.',
    'alpha_dash' => 'The :attribute may only contain letters, numbers, dashes and underscores.',
    'alpha_num' => 'The :attribute may only contain letters and numbers.',
    'array' => 'The :attribute must be an array.',
    'before' => 'The :attribute must be a date before :date.',
    'before_or_equal' => 'The :attribute must be a date before or equal to :date.',
    'between' => [
        'numeric' => ':attribute必须在:min和:max之间', // 'The :attribute must be between :min and :max.',
        'file' => ':attribute大小必须在:min和:max kb之间', // 'The :attribute must be between :min and :max kilobytes.',
        'string' => ':attribute长度必须在:min和:max之间', // 'The :attribute must be between :min and :max characters.',
        'array' => ':attribute记录数必须在:min和:max之间', // 'The :attribute must have between :min and :max items.',
    ],
    'boolean' => 'The :attribute field must be true or false.',
    'confirmed' => ' :attribute 两次输入不一致', // 'The :attribute confirmation does not match.',
    'date' => 'The :attribute is not a valid date.',
    'date_equals' => 'The :attribute must be a date equal to :date.',
    'date_format' => 'The :attribute does not match the format :format.',
    'different' => 'The :attribute and :other must be different.',
    'digits' => 'The :attribute must be :digits digits.',
    'digits_between' => 'The :attribute must be between :min and :max digits.',
    'dimensions' => 'The :attribute has invalid image dimensions.',
    'distinct' => 'The :attribute field has a duplicate value.',
    'email' => '请输入正确的邮箱地址！', // 'The :attribute must be a valid email address.',
    'ends_with' => 'The :attribute must end with one of the following: :values',
    'exists' => 'The selected :attribute is invalid.',
    'file' => 'The :attribute must be a file.',
    'filled' => 'The :attribute field must have a value.',
    'gt' => [
        'numeric' => ':attribute必须大于:max', // 'The :attribute must be greater than :value.',
        'file' => ':attribute大小必须大于:max kb', // 'The :attribute must be greater than :value kilobytes.',
        'string' => ':attribute长度必须大于:max个字符', // 'The :attribute must be greater than :value characters.',
        'array' => ':attribute必须包含大于:max条记录', // 'The :attribute must have more than :value items.',
    ],
    'gte' => [
        'numeric' => ':attribute必须大于等于:max', // 'The :attribute must be greater than or equal :value.',
        'file' => ':attribute大小必须大于等于:max kb', // 'The :attribute must be greater than or equal :value kilobytes.',
        'string' => ':attribute长度必须大于等于:max个字符', // 'The :attribute must be greater than or equal :value characters.',
        'array' => ':attribute必须包含大于等于:max条记录', // 'The :attribute must have :value items or more.',
    ],
    'image' => 'The :attribute must be an image.',
    'in' => 'The selected :attribute is invalid.',
    'in_array' => 'The :attribute field does not exist in :other.',
    'integer' => ' :attribute 必须是整数.',
    'ip' => ' :attribute 必须是有效的IP地址.',
    'ipv4' => 'The :attribute must be a valid IPv4 address.',
    'ipv6' => 'The :attribute must be a valid IPv6 address.',
    'json' => 'The :attribute must be a valid JSON string.',
    'lt' => [
        'numeric' => ':attribute必须小于:max', // 'The :attribute must be less than :value.',
        'file' => ':attribute大小必须小于:max kb', // 'The :attribute must be less than :value kilobytes.',
        'string' => ':attribute长度必须小于:max个字符', // 'The :attribute must be less than :value characters.',
        'array' => ':attribute必须包含小于:max条记录', // 'The :attribute must have less than :value items.',
    ],
    'lte' => [
        'numeric' => ':attribute必须小于等于:max', // 'The :attribute must be less than or equal :value.',
        'file' => ':attribute大小必须小于等于:max kb', // 'The :attribute must be less than or equal :value kilobytes.',
        'string' => ':attribute长度必须小于等于:max个字符', // 'The :attribute must be less than or equal :value characters.',
        'array' => ':attribute必须包含小于等于:max条记录', // 'The :attribute must not have more than :value items.',
    ],
    'max' => [
        'numeric' => ':attribute不能大于:max', // 'The :attribute may not be greater than :max.',
        'file' => ':attribute大小不能超过:max kb', // 'The :attribute may not be greater than :max kilobytes.',
        'string' => ':attribute长度不能超过:max个字符', // 'The :attribute may not be greater than :max characters.',
        'array' => ':attribute不能超过:max条记录', // 'The :attribute may not have more than :max items.',
    ],
    'mimes' => 'The :attribute must be a file of type: :values.',
    'mimetypes' => 'The :attribute must be a file of type: :values.',
    'min' => [
        'numeric' => ':attribute不能小于:min', // 'The :attribute must be at least :min.',
        'file' => ':attribute大小至少:min kb', // 'The :attribute must be at least :min kilobytes.',
        'string' => ':attribute长度至少包含:min个字符', // 'The :attribute must be at least :min characters.',
        'array' => ':attribute至少包含:min条记录', // 'The :attribute must have at least :min items.',
    ],
    'not_in' => 'The selected :attribute is invalid.',
    'not_regex' => 'The :attribute format is invalid.',
    'numeric' => ' :attribute 必须是数字.',
    'password' => '密码不正确', // 'The password is incorrect.',
    'present' => 'The :attribute field must be present.',
    'regex' => 'The :attribute format is invalid.',
    'required' => ':attribute不能为空', // 'The :attribute field is required.',
    'required_if' => 'The :attribute field is required when :other is :value.',
    'required_unless' => 'The :attribute field is required unless :other is in :values.',
    'required_with' => 'The :attribute field is required when :values is present.',
    'required_with_all' => 'The :attribute field is required when :values are present.',
    'required_without' => 'The :attribute field is required when :values is not present.',
    'required_without_all' => 'The :attribute field is required when none of :values are present.',
    'same' => 'The :attribute and :other must match.',
    'size' => [
        'numeric' => 'The :attribute must be :size.',
        'file' => 'The :attribute must be :size kilobytes.',
        'string' => 'The :attribute must be :size characters.',
        'array' => 'The :attribute must contain :size items.',
    ],
    'starts_with' => 'The :attribute must start with one of the following: :values',
    'string' => 'The :attribute must be a string.',
    'timezone' => 'The :attribute must be a valid zone.',
    'unique' => '该:attribute已存在', //  'The :attribute has already been taken.',
    'uploaded' => 'The :attribute failed to upload.',
    'url' => 'The :attribute format is invalid.',
    'uuid' => 'The :attribute must be a valid UUID.',

    /*
    |--------------------------------------------------------------------------
    | Custom Validation Language Lines
    |--------------------------------------------------------------------------
    |
    | Here you may specify custom validation messages for attributes using the
    | convention "attribute.rule" to name the lines. This makes it quick to
    | specify a specific custom language line for a given attribute rule.
    |
    */

    'custom' => [
        'attribute-name' => [
            'rule-name' => 'custom-message',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Custom Validation Attributes
    |--------------------------------------------------------------------------
    |
    | The following language lines are used to swap our attribute placeholder
    | with something more reader friendly such as "E-Mail Address" instead
    | of "email". This simply helps us make our message more expressive.
    |
    */

    'attributes' => [],

];
