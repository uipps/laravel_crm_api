<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\App;

class Language extends Model
{
    const ALLOW_LANGUAGE_LIST = ['zh_CN', 'zh-CN', 'en_US', 'en-US']; // 兼容处理，因为前端变动多次，服务器做软连接

    protected $table = 'sys_language';
    public $timestamps = false;
    protected $appends = ['display_name'];

    protected $fillable = [
        'name',                                 // 显示名称
        'cn_name',                              // 中文名称
        'en_name',                              // 英文名称
        'simple_en_name',                       // 英文简称
        'status',                               // 状态 0无效1有效
    ];

    public function getDisplayNameAttribute()
    {
        if(App::isLocale('zh_CN')){
            return $this->cn_name;
        }else{
            return $this->en_name;
        }
    }
}
