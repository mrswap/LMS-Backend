<?php

namespace App\Modules\Trainee\Support\Controllers;

use App\Events\SupportMessageSent;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSupportMessageRequest;
use App\Jobs\GenerateAiReplyJob;
use App\Models\SupportMessage;
use App\Models\SupportThread;
use App\Models\Topic;
use App\Models\User;
use App\Services\NotificationService;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SupportController extends Controller
{
    protected NotificationService $notification;

    public function __construct(
        NotificationService $notification
    ) {
        $this->notification = $notification;
    }
    /*
    |--------------------------------------------------------------------------
    | INBOX
    |--------------------------------------------------------------------------
    */

    public function inbox()
    {
        $user = auth()->user();

        $threads = SupportThread::query()

            ->with([

                'program',
                'level',
                'module',
                'chapter',
                'topic',

                'latestMessage.sender',
            ])

            /*
            |--------------------------------------------------------------------------
            | UNREAD COUNT
            |--------------------------------------------------------------------------
            */

            ->withCount([

                'messages as unread_count' => function ($q) {

                    $q->whereNull('read_at')

                        /*
                    |--------------------------------------------------------------------------
                    | ONLY ADMIN + AI MESSAGES
                    |--------------------------------------------------------------------------
                    */

                        ->where(function ($query) {

                            $query

                                ->where('is_admin', true)

                                ->orWhere('is_ai', true);
                        });
                },

                'messages',
            ])

            /*
            |--------------------------------------------------------------------------
            | ONLY CURRENT TRAINEE
            |--------------------------------------------------------------------------
            */

            ->where('user_id', $user->id)

            /*
            |--------------------------------------------------------------------------
            | ORDER
            |--------------------------------------------------------------------------
            */

            ->orderByDesc('last_message_at')

            ->orderByDesc('id')

            ->get();

        return response()->json([

            'success' => true,

            'data' => $threads,
        ]);
    }



    /*
    |----------------------------------------------------------------------
    | GET OR CREATE THREAD
    |----------------------------------------------------------------------
    */

    public function thread($topicId)
    {
        $user = auth()->user();

        /*
        |------------------------------------------------------------------
        | LOAD TOPIC
        |------------------------------------------------------------------
        */

        $topic = Topic::with([

            'program',
            'level',
            'module',
            'chapter',

        ])->findOrFail($topicId);

        /*
        |------------------------------------------------------------------
        | CREATE THREAD
        |------------------------------------------------------------------
        */

        $thread = SupportThread::firstOrCreate(

            [

                'user_id' => $user->id,

                'topic_id' => $topic->id,
            ],

            [

                'program_id' =>
                $topic->program_id,

                'level_id' =>
                $topic->level_id,

                'module_id' =>
                $topic->module_id,

                'chapter_id' =>
                $topic->chapter_id,

                'status' =>
                SupportThread::STATUS_OPEN,

                'ai_enabled' => true,

                'last_message_at' =>
                now(),
            ]
        );

        /*
        |------------------------------------------------------------------
        | NOTIFY ADMINS
        |------------------------------------------------------------------
        */

        if ($thread->wasRecentlyCreated) {

            $admins = User::whereHas(
                'role',
                function ($q) {

                    $q->whereIn('name', [

                        'admin',
                        'superadmin',
                        'staff',
                    ]);
                }
            )
                ->where('is_active', true)
                ->get();

            $this->notification->sendToUsers(

                $admins,

                'SUPPORT_THREAD_CREATED',

                [

                    'title' =>
                    'New Topic Clarification',

                    'message' =>

                    $user->name
                        . ' requested clarification for topic: '
                        . $topic->title,

                    'id' => $thread->id,

                    'meta' => [

                        'thread_id' =>
                        $thread->id,

                        'topic_id' =>
                        $topic->id,
                    ]
                ],

                ['db', 'push', 'mail']
            );

            Log::channel('ai')->info(
                'Support Thread Created',
                [
                    'thread_id' => $thread->id,
                    'topic_id' => $topic->id,
                    'user_id' => $user->id,
                ]
            );
        }

        /*
        |------------------------------------------------------------------
        | LOAD RELATIONS
        |------------------------------------------------------------------
        */

        $thread->load([

            'program',
            'level',
            'module',
            'chapter',
            'topic',

            'messages.sender',
        ]);

        /*
        |------------------------------------------------------------------
        | MARK ADMIN MESSAGES READ
        |------------------------------------------------------------------
        */

        $thread->messages()

            ->where('is_admin', true)

            ->whereNull('read_at')

            ->update([

                'read_at' => now(),
            ]);

        /*
        |------------------------------------------------------------------
        | MARK AI MESSAGES READ
        |------------------------------------------------------------------
        */

        $thread->messages()

            ->where('is_ai', true)

            ->whereNull('read_at')

            ->update([

                'read_at' => now(),
            ]);

        return response()->json([

            'success' => true,

            'data' => $thread,
        ]);
    }

    /*
    |----------------------------------------------------------------------
    | SEND MESSAGE
    |----------------------------------------------------------------------
    */

    public function send(
        StoreSupportMessageRequest $request,
        $threadId
    ) {

        DB::beginTransaction();

        try {

            $user = auth()->user();

            /*
            |------------------------------------------------------------------
            | VALID THREAD
            |------------------------------------------------------------------
            */

            $thread = SupportThread::where(
                'user_id',
                $user->id
            )->findOrFail($threadId);

            /*
            |------------------------------------------------------------------
            | REOPEN IF RESOLVED
            |------------------------------------------------------------------
            */

            if ($thread->isResolved()) {

                $thread->reopen();

                /*
                |--------------------------------------------------------------
                | NOTIFY ADMINS
                |--------------------------------------------------------------
                */

                $admins = User::whereHas(
                    'role',
                    function ($q) {

                        $q->whereIn('name', [

                            'admin',
                            'superadmin',
                            'staff',
                        ]);
                    }
                )
                    ->where('is_active', true)
                    ->get();

                $this->notification->sendToUsers(

                    $admins,

                    'SUPPORT_REOPENED',

                    [

                        'title' =>
                        'Clarification Reopened',

                        'message' =>

                        $user->name
                            . ' reopened clarification request.',

                        'id' => $thread->id,

                        'meta' => [

                            'thread_id' =>
                            $thread->id,
                        ]
                    ],

                    ['db', 'push', 'mail']
                );
            }

            /*
            |------------------------------------------------------------------
            | ATTACHMENT
            |------------------------------------------------------------------
            */

            $attachment = null;

            if ($request->hasFile('attachment')) {

                $file = $request->file('attachment');

                $filename =

                    time()
                    . '_'
                    . uniqid()
                    . '.'
                    . $file->getClientOriginalExtension();

                $file->move(

                    public_path(
                        'uploads/support-message'
                    ),

                    $filename
                );

                $attachment =

                    'uploads/support-message/'
                    . $filename;
            }

            /*
            |------------------------------------------------------------------
            | CREATE MESSAGE
            |------------------------------------------------------------------
            */

            $message = SupportMessage::create([

                'thread_id' =>
                $thread->id,

                'sender_id' =>
                $user->id,

                'message' =>
                trim($request->message),

                'attachment' =>
                $attachment,

                'is_admin' => false,

                'is_ai' => false,
            ]);

            /*
            |------------------------------------------------------------------
            | UPDATE THREAD
            |------------------------------------------------------------------
            */

            $thread->update([

                'last_message_at' => now(),
            ]);

            /*
            |------------------------------------------------------------------
            | LOG
            |------------------------------------------------------------------
            */

            Log::channel('ai')->info(
                'Trainee Message Created',
                [
                    'thread_id' => $thread->id,
                    'message_id' => $message->id,
                    'user_id' => $user->id,
                ]
            );

            /*
            |------------------------------------------------------------------
            | BROADCAST
            |------------------------------------------------------------------
            */

            broadcast(
                new SupportMessageSent(
                    $message
                )
            )->toOthers();

            /*
            |------------------------------------------------------------------
            | COMMIT BEFORE JOB
            |------------------------------------------------------------------
            */

            DB::commit();

            /*
            |------------------------------------------------------------------
            | AI AUTO REPLY
            |------------------------------------------------------------------
            */

            if (

                config('ai.auto_reply')
                && $thread->ai_enabled

            ) {

                GenerateAiReplyJob::dispatch(
                    $thread->id
                );

                Log::channel('ai')->info(
                    'AI Job Dispatched',
                    [
                        'thread_id' => $thread->id,
                    ]
                );
            }

            return response()->json([

                'success' => true,

                'message' =>
                'Message sent successfully.',

                'data' =>

                $message->load('sender'),
            ]);
        } catch (\Throwable $e) {

            DB::rollBack();

            Log::channel('ai')->error(
                'Support Send Error',
                [
                    'thread_id' => $threadId,
                    'message' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]
            );

            return response()->json([

                'success' => false,

                'message' =>
                'Failed to send message.',
            ], 500);
        }
    }
}
