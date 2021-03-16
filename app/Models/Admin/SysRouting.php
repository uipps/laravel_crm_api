<?php

namespace App\Models\Admin;

use App\Mappers\CommonMapper;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class SysRouting extends Model
{
    protected $table = 'sys_routing';
    public $timestamps = false;


    public function scopeRouteName(Builder $query, $name)
    {
        return $query->where('url', $name);
    }

    public function privilege()
    {
        return $this->belongsTo(SysPrivilege::class, 'privilege_id')
            ->where('status', CommonMapper::STATUS_SHOW);
    }

    protected $fillable = [
        'name',                                 // 名称 路由名称
        'url',                                  // 路由 后端接口相对路径
        'privilege_id',                         // 权限id 关联权限表id,1对多关系,适用于1个权限对应多个路由的场景
        'sort_no',                              // 序号 控制菜单顺序
        'creator_id',                           // 创建人
        'created_time',                         // 创建时间
        'updator_id',                           // 修改人
        'updated_time',                         // 修改时间
    ];
}
