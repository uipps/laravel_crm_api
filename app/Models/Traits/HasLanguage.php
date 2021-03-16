<?php 

namespace App\Models\Traits;

use App\Mappers\CommonMapper;
use App\Models\Admin\Language;
use Illuminate\Database\Eloquent\Builder;

/**
 * @method static Illuminate\Database\Eloquent\Model active
 * @method static Illuminate\Database\Eloquent\Builder dealList
 */
trait HasLanguage {

    /**
     * 关联sys_language
     */
    public function language()
    {
        return $this->belongsTo(Language::class);
    }
}