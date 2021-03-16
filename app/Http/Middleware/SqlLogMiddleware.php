<?php

namespace App\Http\Middleware;

use Closure;
use App\Libs\Logs\SqlLog;

class SqlLogMiddleware
{
    /**
     * Handle a SQL Log.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next) {
        \DB::listen(function($sql) {
            $logger = new SqlLog(\DB::connection()->getDatabaseName());
            $msg = 'take time: ' . print_r($sql->time, true) . ' , sql: ' . print_r($sql->sql, true);
            $logger->info($msg, $sql->bindings);
        });
        return $next($request);
    }
}
