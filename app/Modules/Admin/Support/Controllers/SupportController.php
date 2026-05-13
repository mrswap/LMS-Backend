<?php

namespace App\Modules\Admin\Support\Controllers;

use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
    | THREAD LIST
    |--------------------------------------------------------------------------
    */

    public function index(Request $request)
    {
        $threads = SupportThread::query()

            ->with([

                'user',
                'program',
                'level',
                'module',
                'chapter',
                'topic',
                'latestMessage.sender',
            ])

            ->withCount([

                'unreadMessages',
                'messages',
            ])

            /*
            |--------------------------------------------------------------------------
            | FILTERS
            |--------------------------------------------------------------------------
            */

            ->when(
                $request->filled('status'),
                function ($q) use ($request) {

                    $q->where(
                        'status',
                        $request->status
                    );
                }
            )

            ->when(
                $request->filled('topic_id'),
                function ($q) use ($request) {

                    $q->where(
                        'topic_id',
                        $request->topic_id
                    );
                }
            )

            ->when(
                $request->filled('user_id'),
                function ($q) use ($request) {

                    $q->where(
                        'user_id',
                        $request->user_id
                    );
                }
            )

            /*
            |--------------------------------------------------------------------------
            | SEARCH
            |--------------------------------------------------------------------------
            */

            ->when(
                $request->filled('search'),
                function ($q) use ($request) {

                    $search = trim($request->search);

                    $q->where(function ($query) use ($search) {

                        $query

                            ->whereHas('user', function ($u) use ($search) {

                                $u->where(
                                    'name',
                                    'LIKE',
                                    "%{$search}%"
                                );
                            })

                            ->orWhereHas('topic', function ($t) use ($search) {

                                $t->where(
                                    'title',
                                    'LIKE',
                                    "%{$search}%"
                                );
                            });
                    });
                }
            )

            /*
            |--------------------------------------------------------------------------
            | ORDER
            |--------------------------------------------------------------------------
            */

            ->orderByDesc('last_message_at')

            ->orderByDesc('id')

            ->paginate(
                $request->per_page ?? 20
            );

        return response()->json([

            'success' => true,

            'data' => $threads,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | SINGLE THREAD
    |--------------------------------------------------------------------------
    */

    public function show($id)
    {
        $thread = SupportThread::with([

            'user',
            'program',
            'level',
            'module',
            'chapter',
            'topic',

            'messages.sender',

        ])->findOrFail($id);

        /*
        |--------------------------------------------------------------------------
        | MARK TRAINEE MESSAGES READ
        |--------------------------------------------------------------------------
        */

        $thread->messages()

            ->where('is_admin', false)

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
    | REPLY
    |--------------------------------------------------------------------------
    */

    public function reply(
        StoreSupportMessageRequest $request,
        $threadId
    ) {

        DB::beginTransaction();

        try {

            $admin = auth()->user();

            $thread = SupportThread::findOrFail($threadId);

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

                'sender_id' => $admin->id,

                'message' => $request->message,

                'attachment' => $attachment,

                'is_admin' => true,
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
            | NOTIFICATION
            |--------------------------------------------------------------------------
            */

            $this->notification->send(

                $thread->user,

                'SUPPORT_REPLY',

                [

                    'title' => 'Admin Replied',

                    'message' =>
                    'Admin replied to your clarification request.',

                    'id' => $thread->id,

                    'meta' => [

                        'thread_id' => $thread->id,
                    ]
                ],

                ['db', 'push', 'mail']
            );

            DB::commit();

            return response()->json([

                'success' => true,

                'message' => 'Reply sent successfully.',

                'data' => $message->load('sender'),
            ]);
        } catch (\Throwable $e) {

            DB::rollBack();

            return response()->json([

                'success' => false,

                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | RESOLVE THREAD
    |--------------------------------------------------------------------------
    */

    public function resolve($id)
    {
        $thread = SupportThread::findOrFail($id);

        $thread->markResolved(auth()->id());

        /*
        |--------------------------------------------------------------------------
        | NOTIFICATION
        |--------------------------------------------------------------------------
        */

        $this->notification->send(

            $thread->user,

            'SUPPORT_RESOLVED',

            [

                'title' => 'Clarification Resolved',

                'message' =>
                'Your clarification request was resolved.',

                'id' => $thread->id,

                'meta' => [

                    'thread_id' => $thread->id,
                ]
            ],

            ['db', 'push', 'mail']
        );

        return response()->json([

            'success' => true,

            'message' => 'Thread resolved successfully.',
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | REOPEN THREAD
    |--------------------------------------------------------------------------
    */

    public function reopen($id)
    {
        $thread = SupportThread::findOrFail($id);

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
                'A trainee reopened clarification request.',

                'id' => $thread->id,

                'meta' => [

                    'thread_id' => $thread->id,
                ]
            ],

            ['db', 'push', 'mail']
        );

        return response()->json([

            'success' => true,

            'message' => 'Thread reopened successfully.',
        ]);
    }
}
