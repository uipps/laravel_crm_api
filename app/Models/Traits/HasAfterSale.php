<?php 

namespace App\Models\Traits;

use App\Models\Admin\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * @method static Illuminate\Database\Eloquent\Model active
 * @method static Illuminate\Database\Eloquent\Builder dealList
 */
trait HasAfterSale {
    
    /**
     * 售后客服
     */
    public function after_sale()
    {
        return $this->belongsTo(User::class, 'after_sale_id');
    }
}

    