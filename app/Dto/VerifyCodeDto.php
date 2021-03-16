<?php

namespace App\Dto;

class VerifyCodeDto extends BaseDto
{
    public function Assign($item) {
        parent::Assign($item);
        if (isset($item['img']))
            $this->encoded = $item['img'];
    }

    public $key = '';
    public $encoded = '';
    public $mime = 'image/png';
    public $expired_at = '';
    public $timezone = 8; // +8 默认东八区，
    public $sensitive = false;
}
