<?php

namespace App\Models\Customer;

use App\Models\Traits\HasDepartment;
use Illuminate\Database\Eloquent\Model;

class CustomerClue extends Model
{
    use HasDepartment;
    
    protected $table = 'customer_clue';
    public $timestamps = false;

    protected $fillable = [
        'department_id',                        // 创建者所属部门ID
        'name',                                 // 客户名称
        'country_id',                           // 国家id
        'language_id',                          // 语言id
        'facebook_id',                          // FacebookID
        'whatsapp_id',                          // WhatsAppID
        'line_id',                              // LineID
        'advisory_type',                        // 咨询类型 1Diet、2ED/Muscle、3Skin、4Other
        'quality_level',                        // 线索质量 1A2B3C4D
        'clue_source',                          // 线索来源
        'remark',                               // 备注 需要作为首条追踪记录
        'distribute_status',                    // 分配状态 0未分配1已分配
        'distribute_time',                      // 分配时间
        'post_sale_id',                         // 售后id
        'opt_status',                           // 处理状态 0未处理1已处理
        'track_num',                            // 追踪次数
        'status',                               // 状态 0停用1启用-1删除
        'finish_status',                        // 成交状态 即归档状态:0未成交1已成交
        'finish_time',                          // 成交时间 即归档时间
        'customer_id',                          // 关联customer表中的id
        'creator_id',                           // 创建人
        'created_time',                         // 创建时间
        'updator_id',                           // 修改人
        'updated_time',                         // 更新时间
        'deleted_time',                         // 删除时间
    ];
}
