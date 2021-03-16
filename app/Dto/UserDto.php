<?php

namespace App\Dto;

class UserDto extends BaseDto
{
    public $id = 0;                             // 唯一id
    public $real_name = '';                     // 姓名
    public $email = '';                         // 邮箱
    public $department_id = 0;                  // 部门id, 单选
    public $department_name = '';               // 部门名称
    public $job_type = 0;                       // 部门类型 0无1售前2售后
    public $job_type_display = '';

    //public $password = '';                    // 密码
    public $phone = '';                         // 手机
    public $level = 0;                          // 岗位 1管理员,2员工
    public $level_name = '';                    // 岗位名称
    public $status = 0;                         // 状态 0关闭1开启-1已删除
    public $status_display = '';                // 状态 0关闭1开启-1已删除
    public $remember_token = '';                // 用户令牌
    public $last_login_ip = '';                 // 最后登录ip
    public $last_login_time = '';               // 最后登录时间
    public $is_clue_sale = 0;                   // 是否线索客服 0不是 1是
    public $web_language = '';                  // 员工选择的页面语言
    public $creator_id = 0;                     // 创建人 创建者 id；默认 0，意为系统创建或没有创建者
    public $updator_id = 0;                     // 修改人 最后一次编辑记录的用户 id；默认值意义同 creator_id 字段
    public $created_time = '';                  // 创建时间
    public $updated_time = '';                  // 修改时间
    public $deleted_time = '';                  // 删除时间
    // 拼装
    public $language_ids = '';      // 多选
    public $language_names = '';                // 语言，逗号连接
    public $role_id = 0;            // 单选
    public $role_name = '';                     // 角色名称

    public $picsrc = '';
    public $authority_list = '';

    public $order_status = 0;                   // 接单状态 0关闭1开启
    public $order_failure_time = '';            // 接单失效时间
    public $language_weight_ratio = [];         // 员工对应各个语言的分单比例

    public $relate_customer_num = 0;            // 员工关联客户数
    public $receive_status = '';
    public $is_super = false;

    public $audit_not_num = 0;                  // 未审核订单数，
    public $erp_id = 0;
}
