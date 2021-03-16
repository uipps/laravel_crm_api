<?php

namespace App\Dto;

class RoleDto extends BaseDto
{
    public $id = 0;                             // 唯一id
    public $name = '';                          // 角色名称
    public $remark = '';                        // 备注
    public $status = 0;                         // 状态 0关闭1开启
    public $auth_flag = 0;                      // 权限标识 0非超级管理员1超级管理员
    public $role_privileges = [];               // 角色权限列表
    public $role_routings = [];                 // 角色路由列表
    public $creator_id = 0;                     // 创建人
    public $created_time = '';                  // 创建时间
    public $updator_id = 0;                     // 修改人
    public $updated_time = '';                  // 更新时间
    public $relate_valid_user = 0;              // 该角色关联的有效用户数
}
