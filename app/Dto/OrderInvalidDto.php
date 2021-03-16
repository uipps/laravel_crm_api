<?php

namespace App\Dto;

class OrderInvalidDto extends BaseDto
{
    //public $id = 0;                             // 唯一id
    //public $part = 0;                           // 分区字段 order_sale_id按10取模
    public $order_sale_id = 0;                  // 订单客服id
    //public $department_id = 0;                  // 部门id
    //public $order_id = 0;                       // 订单id
    //public $order_no = '';                      // 订单号
    public $invalid_type = 0;                   // 无效类别(原因) 0有效1系统判重2审核取消3审核重复
    public $job_type = 0;                       // 岗位类别 1售前2售后
    //public $creator_id = 0;                     // 创建人
    //public $created_time = '';                  // 创建时间
    //public $updator_id = 0;                     // 修改人
    //public $updated_time = '';                  // 更新时间
}
