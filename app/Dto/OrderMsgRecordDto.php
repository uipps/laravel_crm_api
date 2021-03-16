<?php

namespace App\Dto;

class OrderMsgRecordDto extends BaseDto
{
    public $id = 0;                             // 唯一id
    public $month = 0;                          // 分区字段
    public $order_no = '';                      // 订单编码 1订单状态2物流状态
    public $msg_channel = 0;                    // 消息通道
    public $msg_type = '';                      // 消息类别
    public $msg_content = '';                   // 消息内容
    public $result = 0;                         // 处理结果
    public $callback_content = '';              // 回调内容
    public $redo_num = '';                      // 重试次数
    public $creator_id = 0;                     // 创建人
    public $created_time = '';                  // 创建时间
    public $updator_id = 0;                     // 修改人
    public $updated_time = '';                  // 修改时间
}
