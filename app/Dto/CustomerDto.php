<?php

namespace App\Dto;

class CustomerDto extends BaseDto
{
    public function Assign($items) {
        parent::Assign($items);
        if (isset($items['last_contact_time']) && strtotime($items['last_contact_time']) <= 0)
            $this->last_contact_time = '-';
    }

    public $id = 0;                             // 唯一id
    public $customer_name = '';                 // 客户名称 最近使用名称
    //public $type = 0;                           // 客户类型：1-已签收客户、2-未签收客户、3-意向客户
    public $tel = '';                           // 电话号码
    public $country_id = 0;                     // 国家id
    public $country_name = '';
    public $pre_sale_id = 0;                    // 售前客服id 对应用户id
    public $pre_sale_name = '';
    public $after_sale_id = 0;                  // 售后客服id 对应用户id
    public $after_sale_name = '';
    public $order_num = 0;                      // 订单数量
    public $last_contact_time = '';             // 最近联系客户时间
    public $created_time = '';                  // 创建时间
    public $updated_time = '';                  // 修改时间
    public $creator_id = 0;                     // 创建人
    public $updator_id = 0;                     // 修改人
    public $language_id = 0;                    // 语言id
    public $language_name = '';
    public $source_type = 0;                    // 来源类别,1广告2咨询3复购
    public $quality_level = 0;                  // 客户质量,1A2B3C4D
    public $distribution_status = 0;            // 分配状态,0未分配1已分配
    public $received_flag = 0;                  // 签收标识,0未签收1已签
    // TODO
    public $facebook_id = '';                   // FacebookID
    public $whatsapp_id = '';                   // WhatsAppID
    public $line_id = '';                       // LineID
}
