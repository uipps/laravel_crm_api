<?php

namespace App\Dto;

class CustomerStatsDto extends BaseDto
{
    public $customer_num_total = 0;          // 客户数, 下面2项之和：
    public $customer_distribute_no = 0;      // 未分配客户
    public $customer_distribute_yes = 0;     // 已分配客户

    public $clue_num_total = 0;              // 线索数，下面2项之和
    public $clue_distribute_no = 0;          // 未分配线索
    public $clue_distribute_yes = 0;         // 已分配线索
    public $clue_no_dealwith = 0;            // 未处理线索
    public $clue_dealwith = 0;               // 已处理线索
}
