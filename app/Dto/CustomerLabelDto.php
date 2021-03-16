<?php

namespace App\Dto;

class CustomerLabelDto extends BaseDto
{
    public function Assign($items) {
        parent::Assign($items);

        // 进行map映射 TODO 多语言支持
        if (1 == $items['label_type']) $this->label_value = '新客户';
        else if (2 == $items['label_type']) $this->label_value = '复购客户';
        else if (3 == $items['label_type']) $this->label_value = '高签收客户';
        else if (4 == $items['label_type']) $this->label_value = '高拒收客户';
        // 客户质量等级，ABCD
        if (5 == $items['label_type'] && is_numeric($items['label_value'])) {
            if (1 == $items['label_value']) $this->label_value = 'A';
            else if (2 == $items['label_value']) $this->label_value = 'B';
            else if (3 == $items['label_value']) $this->label_value = 'C';
            else if (4 == $items['label_value']) $this->label_value = 'D';
        }
    }

    public $id = 0;                             // 唯一id
    public $customer_id = 0;                    // 客户id
    public $label_type = 0;                     // 标签类别 1新客户,2复购客户,3高签收客户,4高拒收客户,5客户等级
    public $label_style = 0;                    // 标签形式 1单值标签,2多值标签
    public $label_value = '';                   // 标签值 label_style为2时有值,eg:客户等级:A,B,C,D
    public $status = 0;                         // 状态 0无效1有效
    //public $created_time = '';                  // 创建时间
    //public $updated_time = '';                  // 修改时间
    //public $creator_id = 0;                     // 创建人
    //public $updator_id = 0;                     // 修改人
}
