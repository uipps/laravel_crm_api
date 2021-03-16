<?php

namespace App\Dto;

class CustomerClueProcessDto extends BaseDto
{
    public $id = 0;                             // 唯一id
    public $part = 0;                           // 分区字段 process_user_id按10取模
    public $process_user_id = 0;                // 待处理人id
    public $pre_distribute_id = 0;              // 前置分配记录id
    public $clue_id = 0;                        // 线索id
    public $status = 0;                         // 状态 0无效1有效
    public $process_status = 0;                 // 处理状态 0未处理1已处理
    public $process_time = '';                  // 处理时间
    public $created_time = '';                  // 创建时间
    public $updated_time = '';                  // 修改时间
    public $creator_id = 0;                     // 创建人
    public $updator_id = 0;                     // 修改人
}
