<?php

use Illuminate\Support\Facades\Broadcast;

use App\Models\SupportThread;

/*
|--------------------------------------------------------------------------
| BROADCAST AUTH ROUTES
|--------------------------------------------------------------------------
*/

Broadcast::routes([
    'middleware' => ['auth:sanctum']
]);

/*
|--------------------------------------------------------------------------
| SUPPORT THREAD CHANNEL
|--------------------------------------------------------------------------
*/

Broadcast::channel(
    'support.thread.{threadId}',
    function ($user, $threadId) {

        $thread = SupportThread::find($threadId);

        if (! $thread) {
            return false;
        }

        /*
        |--------------------------------------------------------------------------
        | TRAINEE OWNER
        |--------------------------------------------------------------------------
        */

        if ($thread->user_id === $user->id) {
            return true;
        }

        /*
        |--------------------------------------------------------------------------
        | ADMIN / STAFF
        |--------------------------------------------------------------------------
        */

        return in_array(
            $user->role?->name,
            [
                'superadmin',
                'admin',
                'staff',
            ]
        );
    }
);
