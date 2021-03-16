<?php

namespace App\Models\OrderPreSale;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\App;

class OrderOptType extends Model
{
    const AUDIT_PASS_ID_LIST = [11, 12, 13, 14];                // 审核通过类型id列表
    const AUDIT_INVALID_TYPE_ID_LIST = [15, 16, 17, 18, 19];    // 审核为无效单
    const AUDIT_FOLLOW_UP_TYPE_ID_LIST = [20, 21, 22, 23, 24];  // 审核为需要继续跟进

    protected $table = 'order_opt_type';
    public $timestamps = false;

    protected $fillable = [
        'job_type',                             // 岗位类型 1售前2售后
        'name',                                 // 名称
        'en_name',                              // 英文名称
        'status',                               // 状态 0关闭1开启
    ];

    protected $appends = ['display_name'];

    public function getDisplayNameAttribute()
    {
        if(App::isLocale('zh_CN')){
            return $this->name;
        }else{
            return $this->en_name;
        }
    }
}
