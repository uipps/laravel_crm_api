<?php

namespace App\Dto;

class OrderRepeatKeyDto extends BaseDto
{
    public $id = 0;                             // 唯一id
    public $tel_part = 0;                       // 分区字段 手机号码最后一位数字
    public $repeat_key = '';                    // 重复单唯一标识 生成规则:对(电话+商品)进行md5取值
    public $creator_id = 0;                     // 创建人
    public $created_time = '';                  // 创建时间
    public $updator_id = 0;                     // 修改人
    public $updated_time = '';                  // 更新时间
}
