<?php

namespace App\Providers;

use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\ServiceProvider;

class BroadcastServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        /*
        |----------------------------------------------------------------------
        | BROADCAST ROUTES
        |----------------------------------------------------------------------
        */

        Broadcast::routes([
            'middleware' => [
                'api',
                'auth:sanctum',
            ],

            'prefix' => 'api',
        ]);

        /*
        |----------------------------------------------------------------------
        | CHANNELS
        |----------------------------------------------------------------------
        */

        require base_path('routes/channels.php');
    }
}
