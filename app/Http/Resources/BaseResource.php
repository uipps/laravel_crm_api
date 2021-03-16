<?php

namespace App\Http\Resources;

use App\Mappers\CommonMappper;

/**
 * @method static \Illuminate\Database\Eloquent\Builder list
 */

trait BaseResource
{

    // /**
    //  * 转为对象
    //  * @param  $table
    //  *
    //  */
    // public function statusObject($table = '', $statusVal = null)
    // {
    //     $model = $this->getModel();
    //     $table = $table ?: $model->getTable();
    //     $statusVal = is_null($statusVal) ? $model->status : $statusVal;
        
    //     $model->formatted_status = [
    //         'key' => $statusVal,
    //         'value' => CommonMappper::getDict($table.':status.'.$statusVal)
    //     ];
    // }

    // /**
    //  * 转为对象
    //  * @param  $table
    //  *
    //  */
    // public function typeObject($table = '', $typeVal = null)
    // {
        
    //     $model = $this->getModel();
    //     $table = $table ?: $model->getTable();
    //     $typeVal = is_null($typeVal) ? $model->type : $typeVal;
        
    //     $model->formatted_type = [
    //         'key' => $typeVal,
    //         'value' => CommonMappper::getDict($table.':type.'.$typeVal)
    //     ];
    // }


    public function makeHidden($add = [], $except = [])
    {
        $hidden = [
            'updator_id', 'updated_time', 'deletor_id', 'deleted_time',
        ];
    
        $hidden = !$add ? $hidden : array_merge($hidden, $add);
        $hidden = !$except ? $hidden : array_diff($hidden, $except);
        
        $this->getModel()->makeHidden($hidden);
    }
}
