<?php

namespace App\Dto;

class ResponseDto extends BaseDto
{
    public $status = self::SUCCESS_CODE; // 0表示成功，非0表示失败
    public $msg  = '';                 // 失败原因描述
    public $data = [];                 // 存放数据
}
