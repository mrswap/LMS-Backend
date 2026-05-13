<?php

namespace App\Modules\Trainee\Support\Controllers;

use App\Http\Controllers\Controller;

use Illuminate\Support\Facades\DB;
use App\Events\SupportMessageSent;
use App\Models\Topic;
use App\Models\SupportThread;
use App\Models\SupportMessage;
use App\Models\User;

use App\Http\Requests\StoreSupportMessageRequest;

use App\Services\NotificationService;

class SupportController extends Controller
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

    public function thread($topicId)
    {
        $user = auth()->user();

        $topic = Topic::with([
            'program',
            'level',
            'module',
            'chapter',
        ])->findOrFail($topicId);

        /*
        |--------------------------------------------------------------------------
        | THREAD
        |--------------------------------------------------------------------------
        */

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
        | NOTIFICATION ON FIRST CREATE
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

        /*
        |--------------------------------------------------------------------------
        | LOAD RELATIONS
        |--------------------------------------------------------------------------
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
        |--------------------------------------------------------------------------
        | MARK ADMIN MESSAGES READ
        |--------------------------------------------------------------------------
        */

        $thread->messages()

            ->where('is_admin', true)

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
    |--------------------------------------------------------------------------
    | SEND MESSAGE
    |--------------------------------------------------------------------------
    */

    public function send(
        StoreSupportMessageRequest $request,
        $threadId
    ) {

        DB::beginTransaction();

        try {

            $user = auth()->user();

            $thread = SupportThread::where(
                'user_id',
                $user->id
            )->findOrFail($threadId);

            /*
            |--------------------------------------------------------------------------
            | REOPEN IF RESOLVED
            |--------------------------------------------------------------------------
            */

            if ($thread->isResolved()) {

                $thread->reopen();

                /*
                |--------------------------------------------------------------------------
                | NOTIFY ADMINS
                |--------------------------------------------------------------------------
                */

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

                        'title' => 'Clarification Reopened',

                        'message' =>

                        $user->name
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
            | ATTACHMENT
            |--------------------------------------------------------------------------
            */

            $attachment = null;

            if ($request->hasFile('attachment')) {

                $file = $request->file('attachment');

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

                'sender_id' => $user->id,

                'message' => $request->message,

                'attachment' => $attachment,

                'is_admin' => false,
            ]);


            broadcast(
                new SupportMessageSent($message)
            )->toOthers();
            /*
            |--------------------------------------------------------------------------
            | UPDATE THREAD
            |--------------------------------------------------------------------------
            */

            $thread->update([

                'last_message_at' => now(),
            ]);

            DB::commit();

            return response()->json([

                'success' => true,

                'message' =>
                'Message sent successfully.',

                'data' =>
                $message->load('sender'),
            ]);
        } catch (\Throwable $e) {

            DB::rollBack();

            return response()->json([

                'success' => false,

                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
