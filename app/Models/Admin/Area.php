<?php

namespace App\Models\Admin;

use App\Mappers\CommonMapper;
use Illuminate\Database\Eloquent\Model;

/**
 * @method static \Illuminate\Database\Eloquent\Builder state
 * @method static \Illuminate\Database\Eloquent\Builder city
 * @method static \Illuminate\Database\Eloquent\Builder district
 */
class Area extends Model
{
    protected $table = 'sys_area';
    public $timestamps = false;

    /**
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeState($query)
    {
        $query->where('type', CommonMapper::AREA_TYPE_STATE);
        
        return $query;
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeCity($query)
    {
        $query->where('type', CommonMapper::AREA_TYPE_CITY);
        
        return $query;
    }


    /**
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeDistrict($query)
    {
        $query->where('type', CommonMapper::AREA_TYPE_DISTRICT);
        
        return $query;
    }

    protected $fillable = [
        'parent_id',                            // 上级id
        'country_id',                           // 国家id
        'country_code',                         // 国家编码
        'code',                                 // 编码
        'name',                                 // 名称
        'type',                                 // 类别 1省/州2城市3区域
    ];
}
