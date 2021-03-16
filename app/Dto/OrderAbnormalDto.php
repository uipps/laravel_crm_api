<?php

namespace App\Dto;

class OrderAbnormalDto extends BaseDto
{
    //public $id = 0;                             // 唯一id
    //public $part = 0;                           // order_sale_id按10取模
    public $order_sale_id = 0;                  // 订单客服id
    //public $order_id = 0;                       // 订单id
    //public $order_no = '';                      // 订单号
    public $abnormal_type = 0;                  // 异常类别
    public $abnormal_remark = '';               // 异常备注
    public $status_abnormal = 0;                // 状态 0未处理1已处理，数据表中无此字段，对应数据表中status字段
    public $job_type = 0;                       // 岗位类别 1售前2售后
    public $opt_time = '';                      // 处理时间
    //public $creator_id = 0;                     // 创建人
    //public $created_time = '';                  // 创建时间
    //public $updator_id = 0;                     // 修改人
    //public $updated_time = '';                  // 更新时间
}
