<?php

namespace App\Providers;

use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Event;
use Laravel\Telescope\Telescope;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event listener mappings for the application.
     *
     * @var array
     */
    protected $listen = [
        Registered::class => [
            SendEmailVerificationNotification::class,
        ],
    ];

    /**
     * Register any events for your application.
     *
     * @return void
     */
    public function boot()
    {
        parent::boot();

        // 修改`app/Providers/EventServiceProvider.php`, 添加下面监听代码到boot方法中
        // use Laravel\Telescope\Telescope;
        // use Illuminate\Support\Facades\Event;
        Event::listen('laravels.received_request', function ($request, $app) {
            $reflection = new \ReflectionClass(Telescope::class);
            $handlingApprovedRequest = $reflection->getMethod('handlingApprovedRequest');
            $handlingApprovedRequest->setAccessible(true);
            $handlingApprovedRequest->invoke(null, $app) ? Telescope::startRecording() : Telescope::stopRecording();
        });
    }
}
