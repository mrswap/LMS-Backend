<?php

namespace App\Services;

use App\Models\Notification;
use Illuminate\Support\Facades\Log;

class NotificationService
{
    protected $mail, $push;

    public function __construct(MailService $mail, PushService $push)
    {
        $this->mail = $mail;
        $this->push = $push;
    }

    /**
     * Main entry point
     */
    public function send($user, $type, $data = [], $channels = ['all'])
    {
        try {

            // 🔒 Normalize (minimum defaults)
            $payload = [
                'title'   => $data['title'] ?? 'Notification',
                'message' => $data['message'] ?? '',
                'screen'  => $data['screen'] ?? null,
                'id'      => $data['id'] ?? null,
                'image'   => $data['image'] ?? null,
                'link'    => $data['link'] ?? null,
                'meta'    => $data['meta'] ?? [],
            ];

            /*
            |--------------------------------------------------------------------------
            | 📦 DB
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
            }

            /*
            |--------------------------------------------------------------------------
            | 📧 Email
            |--------------------------------------------------------------------------
            */
            if ($this->shouldSend($channels, 'mail') && $user->email) {
                $this->mail->send($user->email, [
                    'subject' => $payload['title'],
                    'title'   => $payload['title'],
                    'message' => $payload['message'],
                    'link'    => $payload['link'],
                    'image'   => $payload['image'],
                ]);
            }

            /*
            |--------------------------------------------------------------------------
            | 📱 Push
            |--------------------------------------------------------------------------
            */
            if ($this->shouldSend($channels, 'push')) {
                $this->push->send($user, [
                    'title'   => $payload['title'],
                    'message' => $payload['message'],
                    'image'   => $payload['image'],
                ], [
                    'type'   => $type,
                    'screen' => $payload['screen'],
                    'id'     => $payload['id'],
                    'extra'  => $payload['meta'],
                    'link'   => $payload['link'],
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('Notification failed', [
                'user_id' => $user->id ?? null,
                'type'    => $type,
                'error'   => $e->getMessage()
            ]);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | 🎯 Channel Control
    |--------------------------------------------------------------------------
    */
    private function shouldSend($channels, $channel)
    {
        return in_array('all', $channels) || in_array($channel, $channels);
    }

    /*
    |--------------------------------------------------------------------------
    | 🧠 Format Messages (Single Source)
    |--------------------------------------------------------------------------
    */
    private function format($type, $data)
    {
        return match ($type) {

            'TRAINING_ASSIGNED' => [
                'title' => 'New Training Assigned',
                'message' => "New level {$data['level_name']} assigned",
                'screen' => 'LevelDetails',
                'id' => $data['level_id'] ?? null
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

            'CERTIFICATE_GENERATED' => [
                'title' => 'Certificate Ready',
                'message' => 'Your certificate is now available',
                'screen' => 'CertificateScreen'
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

    private function sample($user, $level)
    {
        NotificationService::send($user, 'TRAINING_ASSIGNED', [
            'title'   => 'New Training Assigned',
            'message' => 'New level Cardiac Basics assigned',

            'screen'  => 'LevelDetails',
            'id'      => $level->id,

            'image'   => $level->image ?? null,
            'link'    => route('levels.show', $level->id),

            'meta'    => [
                'level_id' => $level->id
            ]
        ]);
    }
}
