<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class NotificationService
{
    protected $mail;

    protected $push;

    public function __construct(
        MailService $mail,
        PushService $push
    ) {
        $this->mail = $mail;
        $this->push = $push;
    }

    /*
    |--------------------------------------------------------------------------
    | 🪵 CUSTOM LOGGER
    |--------------------------------------------------------------------------
    */

    private function log($level, $message, $data = [])
    {
        Log::channel('notification')->{$level}(
            $message,
            $data
        );
    }

    /*
    |--------------------------------------------------------------------------
    | 🚀 SINGLE USER
    |--------------------------------------------------------------------------
    */

    public function send(
        $user,
        $type,
        $data = [],
        $channels = ['all']
    ) {
        try {

            /*
            |--------------------------------------------------------------------------
            | 🔒 VALIDATE USER
            |--------------------------------------------------------------------------
            */

            if (! $user || ! $user->id) {

                $this->log('warning', '⚠ Invalid notification user', [
                    'type' => $type,
                    'data' => $data,
                ]);

                return false;
            }

            /*
            |--------------------------------------------------------------------------
            | 🧠 FORMAT PAYLOAD
            |--------------------------------------------------------------------------
            */

            $formatted = $this->format($type, $data);

            /*
            |--------------------------------------------------------------------------
            | 🔥 MERGE USER DATA
            |--------------------------------------------------------------------------
            */

            $payload = array_merge(
                $formatted,
                $data
            );

            /*
            |--------------------------------------------------------------------------
            | 🛡 NORMALIZE PAYLOAD
            |--------------------------------------------------------------------------
            */

            $payload = [

                'title' => $payload['title']
                    ?? 'Notification',

                'message' => $payload['message']
                    ?? 'New update available',

                'screen' => $payload['screen']
                    ?? null,

                'id' => $payload['id']
                    ?? null,

                'image' => $payload['image']
                    ?? null,

                'link' => $payload['link']
                    ?? null,

                'meta' => is_array($payload['meta'] ?? null)
                    ? $payload['meta']
                    : [],
            ];

            /*
            |--------------------------------------------------------------------------
            | 🪵 START LOG
            |--------------------------------------------------------------------------
            */

            $this->log('info', '🚀 Sending notification', [

                'user_id' => $user->id,

                'type' => $type,

                'channels' => $channels,

                'payload' => $payload,
            ]);

            /*
            |--------------------------------------------------------------------------
            | 📦 DATABASE
            |--------------------------------------------------------------------------
            */

            if ($this->shouldSend($channels, 'db')) {

                Notification::create([

                    'user_id' => $user->id,

                    'type' => $type,

                    'title' => $payload['title'],

                    'message' => $payload['message'],

                    'data' => $payload,
                ]);

                $this->log('info', '✅ DB notification created', [

                    'user_id' => $user->id,

                    'type' => $type,
                ]);
            }

            /*
            |--------------------------------------------------------------------------
            | 📧 EMAIL
            |--------------------------------------------------------------------------
            */

            if (
                $this->shouldSend($channels, 'mail')
                && ! empty($user->email)
            ) {

                $this->mail->send(

                    $user->email,

                    [
                        'subject' => $payload['title'],
                        'title' => $payload['title'],
                        'message' => $payload['message'],
                        'image' => $payload['image'],
                        'link' => $payload['link'],
                    ]
                );

                $this->log('info', '✅ Mail notification sent', [

                    'user_id' => $user->id,

                    'email' => $user->email,

                    'type' => $type,
                ]);
            }

            /*
            |--------------------------------------------------------------------------
            | 📱 PUSH
            |--------------------------------------------------------------------------
            */

            if ($this->shouldSend($channels, 'push')) {

                $this->push->send(

                    $user,

                    [
                        'title' => $payload['title'],
                        'message' => $payload['message'],
                        'image' => $payload['image'],
                    ],

                    [
                        'type' => $type,
                        'screen' => $payload['screen'],
                        'id' => $payload['id'],
                        'extra' => $payload['meta'],
                        'link' => $payload['link'],
                    ]
                );

                $this->log('info', '✅ Push notification processed', [

                    'user_id' => $user->id,

                    'type' => $type,
                ]);
            }

            /*
            |--------------------------------------------------------------------------
            | ✅ SUCCESS
            |--------------------------------------------------------------------------
            */

            return true;
        } catch (\Throwable $e) {

            $this->log('error', '❌ Notification failed', [

                'user_id' => $user->id ?? null,

                'type' => $type,

                'error' => $e->getMessage(),

                'file' => $e->getFile(),

                'line' => $e->getLine(),

                'data' => $data,
            ]);

            return false;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | 👥 MULTIPLE USERS
    |--------------------------------------------------------------------------
    */

    public function sendToUsers(
        $users,
        $type,
        $data = [],
        $channels = ['all']
    ) {

        foreach ($users as $user) {

            if (! $user || ! $user->is_active) {

                $this->log('warning', '⚠ Skipping inactive user', [

                    'user_id' => $user->id ?? null,

                    'type' => $type,
                ]);

                continue;
            }

            $this->send(
                $user,
                $type,
                $data,
                $channels
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | 🛡 ROLE BASED
    |--------------------------------------------------------------------------
    */

    public function sendToRole(
        $roleName,
        $type,
        $data = [],
        $channels = ['all']
    ) {

        $users = User::whereHas('role', function ($q) use ($roleName) {

            $q->where('name', $roleName);
        })
            ->where('is_active', true)
            ->get();

        $this->log('info', '📢 Sending role notification', [

            'role' => $roleName,

            'count' => $users->count(),

            'type' => $type,
        ]);

        $this->sendToUsers(
            $users,
            $type,
            $data,
            $channels
        );
    }

    /*
    |--------------------------------------------------------------------------
    | 🌍 GLOBAL USERS
    |--------------------------------------------------------------------------
    */

    public function sendToAll(
        $type,
        $data = [],
        $channels = ['all']
    ) {

        User::where('is_active', true)

            ->chunk(100, function ($users) use (
                $type,
                $data,
                $channels
            ) {

                $this->log('info', '🌍 Sending global notifications', [

                    'count' => $users->count(),

                    'type' => $type,
                ]);

                $this->sendToUsers(
                    $users,
                    $type,
                    $data,
                    $channels
                );
            });
    }

    /*
    |--------------------------------------------------------------------------
    | 🎯 CHANNEL CONTROL
    |--------------------------------------------------------------------------
    */

    private function shouldSend($channels, $channel)
    {
        return in_array('all', $channels)
            || in_array($channel, $channels);
    }

    /*
    |--------------------------------------------------------------------------
    | 🧠 CENTRALIZED FORMATTER
    |--------------------------------------------------------------------------
    */

    private function format($type, $data = [])
    {
        return match ($type) {

            /*
            |--------------------------------------------------------------------------
            | 🎓 TRAINING ASSIGNED
            |--------------------------------------------------------------------------
            */

            'TRAINING_ASSIGNED' => [

                'title' => $data['title']
                    ?? 'Training Assigned',

                'message' => $data['message']
                    ?? (
                        isset($data['level_name'])
                        ? "New level {$data['level_name']} assigned"
                        : 'New training assigned'
                    ),

                'screen' => $data['screen']
                    ?? 'LevelDetails',

                'id' => $data['id']
                    ?? $data['level_id']
                    ?? null,

                'image' => $data['image']
                    ?? null,

                'link' => $data['link']
                    ?? null,
            ],

            /*
            |--------------------------------------------------------------------------
            | 📘 LESSON COMPLETED
            |--------------------------------------------------------------------------
            */

            'LESSON_COMPLETED' => [

                'title' => $data['title']
                    ?? 'Lesson Completed',

                'message' => $data['message']
                    ?? 'Lesson completed successfully',

                'screen' => $data['screen']
                    ?? 'LessonDetails',

                'id' => $data['id']
                    ?? null,

                'image' => $data['image']
                    ?? null,
            ],

            /*
            |--------------------------------------------------------------------------
            | 📝 ASSESSMENT COMPLETED
            |--------------------------------------------------------------------------
            */

            'ASSESSMENT_COMPLETED' => [

                'title' => $data['title']
                    ?? 'Assessment Completed',

                'message' => $data['message']
                    ?? 'Assessment completed',

                'screen' => $data['screen']
                    ?? 'AssessmentReview',

                'id' => $data['id']
                    ?? null,

                'image' => $data['image']
                    ?? null,
            ],

            /*
            |--------------------------------------------------------------------------
            | 🎖 CERTIFICATE
            |--------------------------------------------------------------------------
            */

            'CERTIFICATE_GENERATED' => [

                'title' => $data['title']
                    ?? 'Certificate Ready',

                'message' => $data['message']
                    ?? 'Your certificate is now available',

                'screen' => $data['screen']
                    ?? 'CertificateScreen',

                'id' => $data['id']
                    ?? null,

                'image' => $data['image']
                    ?? null,
            ],

            /*
            |--------------------------------------------------------------------------
            | 🔐 AUTH
            |--------------------------------------------------------------------------
            */

            'PASSWORD_CHANGED' => [

                'title' => $data['title']
                    ?? 'Password Changed',

                'message' => $data['message']
                    ?? 'Your password was changed successfully',
            ],

            'LOGIN_ALERT' => [

                'title' => $data['title']
                    ?? 'Login Alert',

                'message' => $data['message']
                    ?? 'New login detected',
            ],

            /*
            |--------------------------------------------------------------------------
            | 📢 SYSTEM
            |--------------------------------------------------------------------------
            */

            'SYSTEM' => [

                'title' => $data['title']
                    ?? 'System Notification',

                'message' => $data['message']
                    ?? 'System update available',
            ],

            'ANNOUNCEMENT' => [

                'title' => $data['title']
                    ?? 'Announcement',

                'message' => $data['message']
                    ?? 'New announcement',
            ],

            'SUPPORT_THREAD_CREATED' => [

                'title' => $data['title']
                    ?? 'New Support Request',

                'message' => $data['message']
                    ?? 'A trainee has requested clarification.',

                'screen' => $data['screen']
                    ?? 'SupportThread',

                'id' => $data['id']
                    ?? null,

                'image' => $data['image']
                    ?? null,

                'meta' => $data['meta']
                    ?? [],
            ],

            'SUPPORT_REPLY' => [

                'title' => $data['title']
                    ?? 'New Support Reply',

                'message' => $data['message']
                    ?? 'Admin replied to your clarification request.',

                'screen' => $data['screen']
                    ?? 'SupportThread',

                'id' => $data['id']
                    ?? null,

                'image' => $data['image']
                    ?? null,

                'meta' => $data['meta']
                    ?? [],
            ],

            'SUPPORT_RESOLVED' => [

                'title' => $data['title']
                    ?? 'Support Request Resolved',

                'message' => $data['message']
                    ?? 'Your clarification request was resolved.',

                'screen' => $data['screen']
                    ?? 'SupportThread',

                'id' => $data['id']
                    ?? null,

                'image' => $data['image']
                    ?? null,

                'meta' => $data['meta']
                    ?? [],
            ],

            'SUPPORT_REOPENED' => [

                'title' => $data['title']
                    ?? 'Support Request Reopened',

                'message' => $data['message']
                    ?? 'A support request has been reopened.',

                'screen' => $data['screen']
                    ?? 'SupportThread',

                'id' => $data['id']
                    ?? null,

                'image' => $data['image']
                    ?? null,

                'meta' => $data['meta']
                    ?? [],
            ],
            /*
            |--------------------------------------------------------------------------
            | 🔥 DEFAULT
            |--------------------------------------------------------------------------
            */

            default => [

                'title' => $data['title']
                    ?? 'Notification',

                'message' => $data['message']
                    ?? 'New update available',

                'screen' => $data['screen']
                    ?? null,

                'id' => $data['id']
                    ?? null,

                'image' => $data['image']
                    ?? null,

                'link' => $data['link']
                    ?? null,

                'meta' => $data['meta']
                    ?? [],
            ]
        };
    }
}
