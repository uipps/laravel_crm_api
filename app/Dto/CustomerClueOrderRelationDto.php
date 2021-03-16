<?php

namespace App\Dto;

class CustomerClueOrderRelationDto extends BaseDto
{
    public $id = 0;                             // 唯一id
    public $service_id = 0;                     // 线索id
    public $customer_id = 0;                    // 订单id
    public $status = 0;                         // 状态 0无效1有效
    public $created_time = '';                  // 创建时间
    public $updated_time = '';                  // 修改时间
    public $creator_id = 0;                     // 创建人
    public $updator_id = 0;                     // 修改人
}
