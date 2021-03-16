<?php

namespace App\Dto;

class SysRoutingDto extends BaseDto
{
    public $id = 0;                             // 唯一id
    public $name = '';                          // 名称 路由名称
    public $url = '';                           // 路由 后端接口相对路径
    public $privilege_id = 0;                   // 权限id 关联权限表id,1对多关系,适用于1个权限对应多个路由的场景
    public $sort_no = 0;                        // 序号 控制菜单顺序
    public $creator_id = 0;                     // 创建人
    public $created_time = '';                  // 创建时间
    public $updator_id = 0;                     // 修改人
    public $updated_time = '';                  // 修改时间
}
