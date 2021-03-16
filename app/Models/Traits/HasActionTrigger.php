<?php 

namespace App\Models\Traits;

use App\Libs\Utils\BaseDataMq;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;

/**
 * @var Illuminate\Database\Eloquent\Model ActionTriggerTrait 
 */
trait HasActionTrigger {
    public static function bootHasActionTrigger() {
        
        static::created(self::createActionTrigger('created'));

        static::updated(self::createActionTrigger('updated'));

        static::deleted(self::createActionTrigger('deleted'));
    }

    public static function createActionTrigger($action){
        $callback = function(Model $model) use ($action){
            
            $model->action = $action;
            if($model->isDirty('status')||$model->isDirty('opt_result')||$model->isDirty('audit_status')||request('routeOrderType') == 'redelivery_able'||$model->isDirty('distribute_type')){
                //基础数据变更推送队列
                if(DB::transactionLevel() > 0){
                    BaseDataMq::storeMq($model);
                }else{
                    BaseDataMq::handleMq($model);
                }
            }
            
        };

        return $callback;
    }

    
}