<?php 

namespace App\Mappers;

use Illuminate\Support\Arr;

class CommonMapper {

    // area type
    const AREA_TYPE_STATE = 1;
    const AREA_TYPE_CITY = 2;
    const AREA_TYPE_DISTRICT = 3;


    // show status
    const STATUS_SHOW = 1; //显示
    const STATUS_HIDE = 0; //隐藏
    const STATUS_DELETE = -1; //删除

    // 用户属性类别
    const USER_ATTR_TYPE_LANGUAGE = 1;
    const USER_ATTR_TYPE_ROLE = 3;

    const ROLE_SUPER_AUTH = 1;

    const SUBMIT_OK = 1; // 提交操作
    const SUBMIT_SAVING = 2; // 保存操作

    const LEVEL_MANAGER = 1; //表示主管
    const LEVEL_SERVICE = 2; // 表示员工

    const PRE_SALE = 1;//售前
    const AFTER_SALE = 2; //售后

    const BTN_DISTRIBUTE_NONE = 1; // 没有单可分配
    const BTN_DISTRIBUTE_PROCESS = 2; // 分单中
    const BTN_DISTRIBUTE_PENDING = 3; // 未分单
    const BTN_DISTRIBUTE_AUTH = 4; // 无权限



    public static function getDict($key = null){
        $ret = [
            'area' => [
                self::AREA_TYPE_STATE => "省份",
                self::AREA_TYPE_CITY => "城市",
                self::AREA_TYPE_DISTRICT => "县区",
            ],
        ];

        if(!is_null($key)){
            $ret = Arr::get($ret, $key);
        }

        return $ret;
    }
}