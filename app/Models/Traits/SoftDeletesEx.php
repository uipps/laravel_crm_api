<?php

namespace App\Models\Traits;

use Illuminate\Database\Eloquent\SoftDeletes;

trait SoftDeletesEx
{
    use SoftDeletes;

    /**
     * Boot the soft deleting trait for a model.
     *
     * @return void
     */
    public static function bootSoftDeletes()
    {
        static::addGlobalScope(new SoftDeletingScopeEx);
    }

}
