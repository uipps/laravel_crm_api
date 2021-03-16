<?php

namespace App\Dto;

class OrderAttachmentDto extends BaseDto
{
    public $id = 0;                             // 唯一id
    public $order_id = 0;                       // 订单id
    public $type = 0;                           // 类别 1预付款截图
    public $file_url = '';                      // 相对路径 文件相对路径
    public $status = 0;                         // 状态 0无效1有效-1删除
    public $creator_id = 0;                     // 创建人
    public $created_time = '';                  // 创建时间
    public $updator_id = 0;                     // 修改人
    public $updated_time = '';                  // 修改时间
}
