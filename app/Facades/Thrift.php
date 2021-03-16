<?php
namespace App\Facades;

use Illuminate\Support\Facades\Facade;

/**
 *  @method static \App\Facades\ThriftSingleton getOrderTrace(string $trace_no)
 *  @see \App\Facades\ThriftSingleton
 */
class Thrift extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'thrift';
    }
}