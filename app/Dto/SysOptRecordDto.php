<?php

namespace App\Dto;

class SysOptRecordDto extends BaseDto
{
    public $id = 0;                             // 唯一id
    public $user_id = 0;                        // 用户id
    public $user_ip = '';                       // 用户ip
    public $type = 0;                           // 操作类型 1新增,2编辑,3删除
    public $module = 0;                         // 操作模块 1仓库,2产品,3订单
    public $title = '';                         // 操作标题
    public $req_uri = '';                       // 请求地址
    public $req_content = '';                   // 请求内容
    public $opt_time = '';                      // 操作时间
}
