<?php

namespace App\Dto;

class CustomerClueTrackDto extends BaseDto
{
    public $id = 0;                             // 唯一id
    public $clue_id = 0;                        // 线索id
    public $remark = '';                        // 追踪内容
    public $creator_id = 0;                     // 创建人
    public $creator_name = '';
    public $created_time = '';                  // 创建时间
    public $updator_id = 0;                     // 修改人
    public $updated_time = '';                  // 更新时间
}
