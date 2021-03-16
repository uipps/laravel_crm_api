<?php

namespace App\Models\Customer;

use Illuminate\Database\Eloquent\Model;

class CustomerClueProcess extends Model
{
    protected $table = 'customer_clue_process';
    public $timestamps = false;

    protected $fillable = [
        'part',                                 // 分区字段 process_user_id按10取模
        'process_user_id',                      // 待处理人id
        'pre_distribute_id',                    // 前置分配记录id
        'clue_id',                              // 线索id
        'status',                               // 状态 0无效1有效
        'process_status',                       // 处理状态 0未处理1已处理
        'process_time',                         // 处理时间
        'created_time',                         // 创建时间
        'updated_time',                         // 修改时间
        'creator_id',                           // 创建人
        'updator_id',                           // 修改人
    ];
}
