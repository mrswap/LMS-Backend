<?php

namespace App\Services;

use App\Models\User;
use App\Models\Notification;
use Illuminate\Support\Facades\Log;

class NotificationService
{
    protected $mail;
    protected $push;

    public function __construct(MailService $mail, PushService $push)
    {
        $this->mail = $mail;
        $this->push = $push;
    }

    /*
    |--------------------------------------------------------------------------
    | 🚀 SINGLE USER
    |--------------------------------------------------------------------------
    */

    public function send($user, $type, $data = [], $channels = ['all'])
    {
        try {

            /*
            |--------------------------------------------------------------------------
            | 🔒 Validate User
            |--------------------------------------------------------------------------
            */

            if (!$user || !$user->id) {

                Log::warning('Notification skipped: invalid user', [
                    'type' => $type
                ]);

                return false;
            }

            /*
            |--------------------------------------------------------------------------
            | 🧠 Auto Format
            |--------------------------------------------------------------------------
            */

            $payload = array_merge(
                $this->format($type, $data),
                $data
            );

            /*
            |--------------------------------------------------------------------------
            | 🔒 Normalize Payload
            |--------------------------------------------------------------------------
            */

            $payload = [
                'title'   => $payload['title'] ?? 'Notification',
                'message' => $payload['message'] ?? '',
                'screen'  => $payload['screen'] ?? null,
                'id'      => $payload['id'] ?? null,
                'image'   => $payload['image'] ?? null,
                'link'    => $payload['link'] ?? null,
                'meta'    => $payload['meta'] ?? [],
            ];

            Log::info('🚀 Sending notification', [
                'user_id' => $user->id,
                'type' => $type,
                'channels' => $channels,
            ]);

            /*
            |--------------------------------------------------------------------------
            | 📦 DATABASE
            |--------------------------------------------------------------------------
            */

            if ($this->shouldSend($channels, 'db')) {

                Notification::create([
                    'user_id' => $user->id,
                    'type'    => $type,
                    'title'   => $payload['title'],
                    'message' => $payload['message'],
                    'data'    => $payload,
                ]);

                Log::info('✅ DB notification created', [
                    'user_id' => $user->id
                ]);
            }

            /*
            |--------------------------------------------------------------------------
            | 📧 EMAIL
            |--------------------------------------------------------------------------
            */

            if (
                $this->shouldSend($channels, 'mail')
                && !empty($user->email)
            ) {

                $this->mail->send(
                    $user->email,
                    [
                        'subject' => $payload['title'],
                        'title'   => $payload['title'],
                        'message' => $payload['message'],
                        'link'    => $payload['link'],
                        'image'   => $payload['image'],
                    ]
                );

                Log::info('✅ Mail notification sent', [
                    'user_id' => $user->id,
                    'email' => $user->email
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
                        'title'   => $payload['title'],
                        'message' => $payload['message'],
                        'image'   => $payload['image'],
                    ],
                    [
                        'type'   => $type,
                        'screen' => $payload['screen'],
                        'id'     => $payload['id'],
                        'extra'  => $payload['meta'],
                        'link'   => $payload['link'],
                    ]
                );

                Log::info('✅ Push notification processed', [
                    'user_id' => $user->id
                ]);
            }

            return true;
        } catch (\Throwable $e) {

            Log::error('❌ Notification failed', [
                'user_id' => $user->id ?? null,
                'type'    => $type,
                'error'   => $e->getMessage(),
                'file'    => $e->getFile(),
                'line'    => $e->getLine(),
            ]);

            return false;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | 👥 MULTIPLE USERS
    |--------------------------------------------------------------------------
    */

    public function sendToUsers($users, $type, $data = [], $channels = ['all'])
    {
        foreach ($users as $user) {

            if (!$user || !$user->is_active) {
                continue;
            }

            $this->send($user, $type, $data, $channels);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | 🛡 ROLE BASED
    |--------------------------------------------------------------------------
    */

    public function sendToRole($roleName, $type, $data = [], $channels = ['all'])
    {
        $users = User::whereHas('role', function ($q) use ($roleName) {

            $q->where('name', $roleName);
        })
            ->where('is_active', true)
            ->get();

        Log::info('📢 Sending role notification', [
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

    public function sendToAll($type, $data = [], $channels = ['all'])
    {
        User::where('is_active', true)
            ->chunk(100, function ($users) use ($type, $data, $channels) {

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

            'TRAINING_ASSIGNED' => [
                'title' => 'New Training Assigned',
                'message' => "New level {$data['level_name']} assigned",
                'screen' => 'LevelDetails',
                'id' => $data['level_id'] ?? null
            ],

            'LESSON_COMPLETED' => [
                'title' => 'Lesson Completed',
                'message' => $data['message']
                    ?? 'Lesson completed successfully',

                'screen' => 'LessonDetails',

                'id' => $data['id'] ?? null
            ],


            'LESSON_REMINDER' => [
                'title' => 'Pending Lesson Reminder',
                'message' => 'You have pending lessons to complete',
                'screen' => 'LessonList'
            ],

            'QUIZ_REMINDER' => [
                'title' => 'Quiz Pending',
                'message' => 'Complete your pending quiz',
                'screen' => 'QuizList'
            ],

            'ASSESSMENT_RESULT' => [
                'title' => 'Assessment Result',
                'message' => "You scored {$data['score']}%",
                'screen' => 'ResultScreen'
            ],

            'ASSESSMENT_COMPLETED' => [
                'title' => 'Assessment Completed',
                'message' => $data['message'] ?? 'Assessment completed',
                'screen' => 'AssessmentReview',
                'id' => $data['id'] ?? null
            ],

            'CERTIFICATE_GENERATED' => [
                'title' => 'Certificate Ready',
                'message' => 'Your certificate is now available',
                'screen' => 'CertificateScreen'
            ],

            'ACCOUNT_CREATED' => [
                'title' => 'Account Created',
                'message' => 'Your account has been created'
            ],

            'PASSWORD_CHANGED' => [
                'title' => 'Password Changed',
                'message' => 'Your password was changed successfully'
            ],

            'LOGIN_ALERT' => [
                'title' => 'Login Alert',
                'message' => 'New login detected'
            ],

            'SYSTEM' => [
                'title' => 'System Notification',
                'message' => $data['message'] ?? 'System update available'
            ],

            'ANNOUNCEMENT' => [
                'title' => 'Announcement',
                'message' => $data['message'] ?? 'New announcement'
            ],

            'AUTH' => [
                'title' => 'Account Notification',
                'message' => $data['message'] ?? 'Account update'
            ],

            default => [
                'title' => 'Notification',
                'message' => 'New update available'
            ]
        };
    }
}
