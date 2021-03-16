<?php

namespace App\Models\Admin;

use App\Mappers\CommonMapper;
use App\Models\Traits\HasBase;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class SysPrivilege extends Model
{
    use HasBase;
    protected $table = 'sys_privilege';
    public $timestamps = false;

    protected $guarded = ['id']; 
    
    protected $hidden = ['pivot'];

    public function roles()
    {
        return $this->belongsToMany(
            Role::class,
            'role_privilege',
            'privilege_id',
            'role_id'
        );
    }

    public function getIsPermittedAttribute()
    {
        if (!auth('api')->check()) {
            return false;
        }

        $roles = auth('api')->user()->roles;

        $superRoles = $roles->where('is_super', true);
        if ($superRoles->isNotEmpty()) {
            return true;
        }

        $requiredRoles = $this->roles;
        if ($requiredRoles->isEmpty()) {
            return false;
        }

        return $requiredRoles->pluck('id')
            ->intersect($roles->pluck('id'))
            ->isNotEmpty();
    }

    protected $fillable = [
        'parent_id',                            // 上级id
        'name',                                 // 名称 权限名称
        'code',                                 // 编码 前端映射编码
        'sort_no',                              // 序号
        'is_menu',                              // 是否未菜单
        'icon',                                 // 图标
        'status',                               // 状态
        'creator_id',                           // 创建人
        'created_time',                         // 创建时间
        'updator_id',                           // 修改人
        'updated_time',                         // 修改时间
    ];
}
