<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

use App\Models\User;
use App\Models\Topic;

use App\Models\SupportThread;
use App\Models\SupportMessage;

class SupportService
{
    protected $notification;

    public function __construct(
        NotificationService $notification
    ) {
        $this->notification = $notification;
    }

    /*
    |--------------------------------------------------------------------------
    | GET OR CREATE THREAD
    |--------------------------------------------------------------------------
    */

    public function getOrCreateThread(
        $user,
        Topic $topic
    ) {

        $thread = SupportThread::firstOrCreate(

            [

                'user_id' => $user->id,

                'topic_id' => $topic->id,
            ],

            [

                'program_id' => $topic->program_id,

                'level_id' => $topic->level_id,

                'module_id' => $topic->module_id,

                'chapter_id' => $topic->chapter_id,

                'status' => SupportThread::STATUS_OPEN,

                'last_message_at' => now(),
            ]
        );

        /*
        |--------------------------------------------------------------------------
        | NOTIFY ON FIRST CREATE
        |--------------------------------------------------------------------------
        */

        if ($thread->wasRecentlyCreated) {

            $admins = User::whereHas('role', function ($q) {

                $q->whereIn('name', [

                    'admin',
                    'superadmin',
                    'staff',
                ]);
            })
                ->where('is_active', true)
                ->get();

            $this->notification->sendToUsers(

                $admins,

                'SUPPORT_THREAD_CREATED',

                [

                    'title' => 'New Topic Clarification',

                    'message' =>

                    $user->name
                        . ' requested clarification for topic: '
                        . $topic->title,

                    'id' => $thread->id,

                    'meta' => [

                        'thread_id' => $thread->id,

                        'topic_id' => $topic->id,
                    ]
                ],

                ['db', 'push', 'mail']
            );
        }

        return $thread;
    }

    /*
    |--------------------------------------------------------------------------
    | SEND MESSAGE
    |--------------------------------------------------------------------------
    */

    public function sendMessage(
        SupportThread $thread,
        $sender,
        $messageText = null,
        $file = null,
        $isAdmin = false
    ) {

        DB::beginTransaction();

        try {

            /*
            |--------------------------------------------------------------------------
            | REOPEN IF RESOLVED
            |--------------------------------------------------------------------------
            */

            if (
                ! $isAdmin
                && $thread->isResolved()
            ) {

                $thread->reopen();

                $this->notifyAdminsThreadReopened(
                    $thread,
                    $sender
                );
            }

            /*
            |--------------------------------------------------------------------------
            | ATTACHMENT
            |--------------------------------------------------------------------------
            */

            $attachment = null;

            if ($file) {

                $filename = time()
                    . '_'
                    . uniqid()
                    . '.'
                    . $file->getClientOriginalExtension();

                $file->move(

                    public_path('uploads/support-message'),

                    $filename
                );

                $attachment =
                    'uploads/support-message/'
                    . $filename;
            }

            /*
            |--------------------------------------------------------------------------
            | CREATE MESSAGE
            |--------------------------------------------------------------------------
            */

            $message = SupportMessage::create([

                'thread_id' => $thread->id,

                'sender_id' => $sender->id,

                'message' => $messageText,

                'attachment' => $attachment,

                'is_admin' => $isAdmin,
            ]);

            /*
            |--------------------------------------------------------------------------
            | UPDATE THREAD
            |--------------------------------------------------------------------------
            */

            $thread->update([

                'status' => SupportThread::STATUS_OPEN,

                'last_message_at' => now(),
            ]);

            /*
            |--------------------------------------------------------------------------
            | NOTIFICATIONS
            |--------------------------------------------------------------------------
            */

            if ($isAdmin) {

                $this->notifyTraineeReply(
                    $thread
                );
            }

            DB::commit();

            return $message->load('sender');
        } catch (\Throwable $e) {

            DB::rollBack();

            throw $e;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | RESOLVE
    |--------------------------------------------------------------------------
    */

    public function resolveThread(
        SupportThread $thread,
        $adminId
    ) {

        $thread->markResolved($adminId);

        $this->notification->send(

            $thread->user,

            'SUPPORT_RESOLVED',

            [

                'title' =>
                'Clarification Resolved',

                'message' =>
                'Your clarification request was resolved.',

                'id' => $thread->id,

                'meta' => [

                    'thread_id' => $thread->id,
                ]
            ],

            ['db', 'push', 'mail']
        );
    }

    /*
    |--------------------------------------------------------------------------
    | REOPEN
    |--------------------------------------------------------------------------
    */

    public function reopenThread(
        SupportThread $thread
    ) {

        $thread->reopen();

        $this->notifyAdminsThreadReopened(
            $thread
        );
    }

    /*
    |--------------------------------------------------------------------------
    | ADMIN REOPEN NOTIFY
    |--------------------------------------------------------------------------
    */

    private function notifyAdminsThreadReopened(
        SupportThread $thread,
        $user = null
    ) {

        $admins = User::whereHas('role', function ($q) {

            $q->whereIn('name', [

                'admin',
                'superadmin',
                'staff',
            ]);
        })
            ->where('is_active', true)
            ->get();

        $this->notification->sendToUsers(

            $admins,

            'SUPPORT_REOPENED',

            [

                'title' =>
                'Clarification Reopened',

                'message' => ($user?->name ?? 'Trainee')
                    . ' reopened clarification request.',

                'id' => $thread->id,

                'meta' => [

                    'thread_id' => $thread->id,
                ]
            ],

            ['db', 'push', 'mail']
        );
    }

    /*
    |--------------------------------------------------------------------------
    | TRAINEE REPLY NOTIFY
    |--------------------------------------------------------------------------
    */

    private function notifyTraineeReply(
        SupportThread $thread
    ) {

        $this->notification->send(

            $thread->user,

            'SUPPORT_REPLY',

            [

                'title' =>
                'Admin Replied',

                'message' =>
                'Admin replied to your clarification request.',

                'id' => $thread->id,

                'meta' => [

                    'thread_id' => $thread->id,
                ]
            ],

            ['db', 'push', 'mail']
        );
    }
}
