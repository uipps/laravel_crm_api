<?php

namespace App\Dto;

class SysPrivilegeDto extends BaseDto
{
    public $id = 0;                             // 唯一id
    public $parent_id = 0;                      // 上级id
    public $name = '';                          // 名称 权限名称，多语言支持
    public $code = '';                          // 编码 前端映射编码
    public $sort_no = 0;                        // 序号
    public $is_menu = 0;                        // 是否未菜单
    public $icon = '';                          // 图标
    public $status = 0;                         // 状态
    public $creator_id = 0;                     // 创建人
    public $created_time = '';                  // 创建时间
    public $updator_id = 0;                     // 修改人
    public $updated_time = '';                  // 修改时间
    public $level = 0;
    public $children = [];                      // 子节点
}
