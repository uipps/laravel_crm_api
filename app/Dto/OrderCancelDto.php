<?php

namespace App\Dto;

class OrderCancelDto extends BaseDto
{
    //public $id = 0;                             // 唯一id 订单id
    //public $order_id = 0;                       // 订单id
    //public $order_no = '';                      // 订单号
    public $remark = '';                        // 备注
    public $status_cancel = 0;                  // 状态 0未提交1已提交 2已归档
    public $opt_result = 0;                     // 处理结果 0未处理1成功-1失败
    public $opt_time = '';                      // 处理时间
    //public $creator_id = 0;                     // 创建人
    //public $created_time = '';                  // 创建时间
    //public $updator_id = 0;                     // 修改人
    //public $updated_time = '';                  // 更新时间
}
