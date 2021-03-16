<?php

namespace App\Http\Controllers;


use App\Models\Admin\Language;

class CommonController extends Controller
{
    use Responsable;

    public function __construct()
    {
        // 临时用于设置语言，便于调试，支持参数强制设置语言，当前只支持2种语言：默认是en，可设置为简体中文cn
        // $lang_code = request()->header('locale'); // 默认使用header头信息中的locale
        // $request = request()->only('locale');
        // if (isset($request['locale']) && in_array($request['locale'], Language::ALLOW_LANGUAGE_LIST))
        //     $lang_code = $request['locale'];
        // $lang_code = str_replace('-','_', $lang_code);
        // if (in_array($lang_code, Language::ALLOW_LANGUAGE_LIST)) app()->setLocale($lang_code);

        \bcscale(10); // 进度计算精度设置
    }

    // 返回json，前端js通常需要对象，对于连续数组要转换为引号数字索引，js要求都是对象，不能一会儿数组，一会儿对象。
    public function response_json($dataDto, $options=0)
    {
        return response()->json($dataDto)->setEncodingOptions($options)->header('Content-Type', 'application/json');
        // return response()->json($dataDto); // 这样好像也行
    }

    protected function directReturn($data)
    {
        return response($data, 200)->header('Content-Type', 'application/json');
    }
}
