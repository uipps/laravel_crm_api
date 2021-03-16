<?php

namespace App\Dto;

class TokenDto extends BaseDto
{
    public $token = '';
    public $token_type = 'bearer';
    public $expires_in = 1;
}
