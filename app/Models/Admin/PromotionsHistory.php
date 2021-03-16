<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;

class PromotionsHistory extends Model
{
    protected $table = 'promotions_history';
    public $timestamps = false;

    protected $fillable = [
        'promotions_id',
        'promotions_version',
        'promotions_detail',
        'creator_id',
        'created_time',
        'updator_id',
        'updated_time',
        'deleted_time',
    ];

    


}
