<?php

namespace App\Models\Customer;

use Illuminate\Database\Eloquent\Model;

class CustomerClueTrack extends Model
{
    protected $table = 'customer_clue_track';
    public $timestamps = false;

    protected $fillable = [
        'clue_id',                              // 线索id
        'remark',                               // 追踪内容
        'creator_id',                           // 创建人
        'created_time',                         // 创建时间
        'updator_id',                           // 修改人
        'updated_time',                         // 更新时间
    ];
}
