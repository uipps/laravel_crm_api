<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;

class PostCode extends Model
{
    protected $table = 'sys_post_code';
    public $timestamps = false;

    protected $fillable = [
        'area_id',                              // 区域id
        'post_code',                            // 邮编
    ];
}
