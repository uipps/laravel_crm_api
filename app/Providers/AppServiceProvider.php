<?php

namespace App\Providers;

use App\Libs\Utils\BaseDataMq;
use Illuminate\Database\Events\TransactionCommitted;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        //if (config('app.env') == 'local')
        \DB::connection()->enableQueryLog(); // 开启sql日志记录

        // 监听事务提交事件
        \DB::getEventDispatcher()->listen(TransactionCommitted::class, function(){
            BaseDataMq::batchHandleMq();
        });
    }
}
