<?php

use Illuminate\Support\Facades\Broadcast;

use App\Models\SupportThread;

Broadcast::channel(
    'support.thread.{threadId}',
    function ($user, $threadId) {

        $thread = SupportThread::find($threadId);

        if (! $thread) {
            return false;
        }

        /*
        |--------------------------------------------------------------------------
        | THREAD OWNER
        |--------------------------------------------------------------------------
        */

        if ((int) $thread->user_id === (int) $user->id) {
            return true;
        }

        /*
        |--------------------------------------------------------------------------
        | LOAD ROLE SAFELY
        |--------------------------------------------------------------------------
        */

        $roleName = optional(
            $user->role()->first()
        )->name;

        /*
        |--------------------------------------------------------------------------
        | ADMIN ACCESS
        |--------------------------------------------------------------------------
        */

        return in_array(
            $roleName,
            [
                'superadmin',
                'admin',
                'staff',
            ]
        );
    }
);