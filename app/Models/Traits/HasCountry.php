<?php 

namespace App\Models\Traits;

use App\Mappers\CommonMapper;
use App\Models\Admin\Country;
use Illuminate\Database\Eloquent\Builder;

/**
 * @method static Illuminate\Database\Eloquent\Model active
 * @method static Illuminate\Database\Eloquent\Builder dealList
 */
trait HasCountry {
    
    /**
     * 关联country
     */
    public function country()
    {
        return $this->belongsTo(Country::class);
    }
}