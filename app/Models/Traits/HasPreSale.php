<?php 

namespace App\Models\Traits;

use App\Models\Admin\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * @method static Illuminate\Database\Eloquent\Model active
 * @method static Illuminate\Database\Eloquent\Builder dealList
 */
trait HasPreSale {
    
    /**
     * 售前客服
     */
    public function pre_sale()
    {
        return $this->belongsTo(User::class, 'pre_sale_id');
    }
}
