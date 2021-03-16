<?php

namespace App\Dto;

class CustomerRemarkDto extends BaseDto
{
    public $id = 0;                             // 唯一id
    public $customer_id = 0;                    // 客户id
    public $job_type = 1;                       // 1-售前 2-售后
    public $sale_name = '';                     // 客服姓名
    public $remark = '';                        // 备注
    public $created_time = '';                  // 创建时间
    public $updated_time = '';                  // 修改时间
    public $creator_id = 0;                     // 创建人
    public $updator_id = 0;                     // 修改人
}
