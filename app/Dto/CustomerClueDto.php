<?php

namespace App\Dto;

class CustomerClueDto extends BaseDto
{
    public $id = 0;                             // 唯一id
    public $department_id = 0;                  // 部门id
    public $department_name = '';
    public $name = '';                          // 客户名称
    public $country_id = 0;                     // 国家id
    public $country_name = '';
    public $language_id = 0;                    // 语言id
    public $language_name = '';
    public $facebook_id = '';                   // FacebookID
    public $whatsapp_id = '';                   // WhatsAppID
    public $line_id = '';                       // LineID
    public $advisory_type = 0;                  // 咨询类型 1Diet、2ED/Muscle、3Skin、4Other
    public $quality_level = 0;                  // 线索质量 1A2B3C4D
    public $clue_source = '';                   // 线索来源
    public $remark = '';                        // 备注 需要作为首条追踪记录
    public $distribute_status = 0;              // 分配状态 0未分配1已分配
    public $distribute_time = '';               // 分配时间
    public $post_sale_id = 0;                   // 分配客服，售后id
    public $post_sale_name = '';                   // 分配的客服，即分配的售后客服
    public $opt_status = 0;                     // 处理状态 0未处理1已处理
    public $track_num = 0;                      // 追踪次数
    public $status = 0;                         // 状态 0停用1启用-1删除
    public $finish_status = 0;                  // 成交状态 即归档状态:0未成交1已成交
    public $finish_time = '';                   // 成交时间 即归档时间
    public $creator_id = 0;                     // 创建人
    public $creator_name = '';                     // 线索客服
    public $created_time = '';                  // 创建时间
    public $updator_id = 0;                     // 修改人
    public $updated_time = '';                  // 更新时间
    public $deleted_time = '';                  // 删除时间
}
