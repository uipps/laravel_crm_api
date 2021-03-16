<?php

namespace App\Models\Admin;

use App\Mappers\CommonMapper;
use App\Models\Traits\HasBase;
use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    use HasBase;

    protected $table = 'role';
    public $timestamps = false;

    protected $fillable = [
        'name',                                 // 角色名称
        'remark',                               // 备注
        'status',                               // 状态 0关闭1开启
        'auth_flag',                            // 权限标识 0非超级管理员1超级管理员
        'creator_id',                           // 创建人
        'created_time',                         // 创建时间
        'updator_id',                           // 修改人
        'updated_time',                         // 更新时间
    ];

    public function getIsSuperAttribute()
    {
        return $this->auth_flag == CommonMapper::ROLE_SUPER_AUTH;
    }

    public function privileges()
    {
        return $this
            ->belongsToMany(
                SysPrivilege::class,
                'role_privilege',
                'role_id',
                'privilege_id'
            )
            ->where(
                'sys_privilege.status',
                '=',
                CommonMapper::STATUS_SHOW
            );
    }

}
