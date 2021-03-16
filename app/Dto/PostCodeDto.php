<?php

namespace App\Dto;

class PostCodeDto extends BaseDto
{
    public $id = 0;                             // 唯一id
    public $area_id = 0;                        // 区域id
    public $post_code = '';                     // 邮编
}
