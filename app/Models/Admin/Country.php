<?php

namespace App\Models\Admin;

use App\Mappers\CommonMapper;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\App;

/**
 * @method static \Illuminate\Database\Eloquent\Builder active
 */
class Country extends Model
{
    protected $table = 'sys_country';
    public $timestamps = false;
    protected $appends = ['display_name'];

    /**
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeActive($query)
    {
        $query->where('status', CommonMapper::STATUS_SHOW);
        
        return $query;
    }

    public function getDisplayNameAttribute()
    {
        if(App::isLocale('zh_CN')){
            return $this->cn_name;
        }else{
            return $this->en_name;
        }
    }

    protected $fillable = [
        'cn_name',                              // 中文名称
        'en_name',                              // 英文名称
        'simple_en_name',                       // 英文简称
        'code',                                 // 编码
        'simple_code',                          // 简单编码
        'phone_code',                           // 区号
        'timezone_value',                       // 时区值
        'status',                               // 状态
        'web_code',                             // 前端标识
    ];

    public function currency()
    {
        return $this->hasOne(Currency::class, 'country_code', 'code');
    }
}
