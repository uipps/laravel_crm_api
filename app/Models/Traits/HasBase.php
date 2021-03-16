<?php 

namespace App\Models\Traits;

use App\Mappers\CommonMapper;
use Illuminate\Database\Eloquent\Builder;

/**
 * @method static Illuminate\Database\Eloquent\Model active
 * @method static Illuminate\Database\Eloquent\Builder dealList
 */
trait HasBase {
    
    public function scopeActive(Builder $query)
    {
        return $query->where('status', CommonMapper::STATUS_SHOW);
    }

     /**
     * 查询列表的作用域
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeDealList($query)
    {
        $model = $query->getModel();
        return $query->orderByDesc($model->primaryKey);
    }
}