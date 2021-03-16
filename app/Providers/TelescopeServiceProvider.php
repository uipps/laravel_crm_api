<?php

namespace App\Providers;

use App\Fakers\UserFaker;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Laravel\Telescope\IncomingEntry;
use Laravel\Telescope\Telescope;
use Laravel\Telescope\TelescopeApplicationServiceProvider;

class TelescopeServiceProvider extends TelescopeApplicationServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        // Telescope::night();

        $this->hideSensitiveRequestDetails();

        Telescope::tag(function (IncomingEntry $entry) {
            if ($entry->type === 'request') {
                return ['method:'.$entry->content['method']];
            }
    
            return [];
        });

        Telescope::filter(function (IncomingEntry $entry) {
            

            $logAble = true;
            if($entry->type == 'query') {
                $sql = $entry->content['sql'];

                if(strpos($sql,'telescope_entries') || strpos($sql,'telescope_entries_tags')){
                    $logAble = false;
                }
            }

            if($logAble) Log::channel('telescope')->info($entry->type, $entry->toArray());

            return $this->app->environment('production') ? false : true;
        });
    }


    /**
     * Prevent sensitive request details from being logged by Telescope.
     *
     * @return void
     */
    protected function hideSensitiveRequestDetails()
    {
        if (!$this->app->isProduction()) {
            return;
        }

        Telescope::hideRequestParameters(['_token']);

        Telescope::hideRequestHeaders([
            'cookie',
            'x-csrf-token',
            'x-xsrf-token',
        ]);
    }

    /**
     * Register the Telescope gate.
     *
     * This gate determines who can access Telescope in non-local environments.
     *
     * @return void
     */
    protected function gate()
    {
        // Gate::define('viewTelescope', function ($user) {
        //     return in_array($user->email, UserFaker::getTelescopeAdmins());
        // });
    }
}
