<?php

namespace App\Modules\Trainee\Content\Controllers;

use App\Http\Controllers\Controller;
use App\Models\UserContentProgress;
use App\Services\AuditService;
use App\Services\NotificationService;

class ContentProgressController extends Controller
{
    public function toggle($contentId)
    {
        $user = auth()->user();

        /*
        |------------------------------------------------------------------
        | CONTENT CHECK
        |------------------------------------------------------------------
        */

        $content = \App\Models\TopicContent::find($contentId);

        if (!$content) {

            return response()->json([
                'success' => false,
                'message' => 'Content not found'
            ], 404);
        }

        /*
        |------------------------------------------------------------------
        | EXISTING PROGRESS
        |------------------------------------------------------------------
        */

        $progress = UserContentProgress::firstOrCreate(

            [
                'user_id' => $user->id,
                'topic_content_id' => $contentId
            ],

            [
                'is_read' => true,
                'read_at' => now()
            ]
        );

        /*
        |------------------------------------------------------------------
        | ALREADY READ
        |------------------------------------------------------------------
        */

        if ($progress->is_read) {

            return response()->json([
                'success' => true,
                'is_read' => true,
                'message' => 'Already marked as read'
            ]);
        }

        /*
        |------------------------------------------------------------------
        | MARK READ
        |------------------------------------------------------------------
        */

        $progress->update([
            'is_read' => true,
            'read_at' => now()
        ]);

        /*
        |------------------------------------------------------------------
        | AUDIT
        |------------------------------------------------------------------
        */

        AuditService::log(
            'lesson_completed',
            'User marked lesson as completed',
            [
                'content_id' => $contentId,
                'user_id' => $user->id
            ]
        );

        /*
        |------------------------------------------------------------------
        | USER NOTIFICATION
        |------------------------------------------------------------------
        */

        app(NotificationService::class)->send(
            $user,
            'LESSON_COMPLETED',
            [
                'title' => 'Lesson Completed',

                'message' => 'You completed a lesson successfully',

                'screen' => 'LessonDetails',

                'id' => $contentId,

                'meta' => [
                    'content_id' => $contentId
                ]
            ]
        );

        /*
        |------------------------------------------------------------------
        | ADMIN PAYLOAD
        |------------------------------------------------------------------
        */

        $adminPayload = [

            'title' => 'Lesson Completed',

            'message' => "{$user->name} completed a lesson",

            'screen' => 'LessonDetails',

            'id' => $contentId,

            'meta' => [
                'content_id' => $contentId,
                'user_id' => $user->id,
                'user_name' => $user->name,
            ]
        ];

        app(NotificationService::class)->sendToRole(
            'admin',
            'LESSON_COMPLETED',
            $adminPayload,
            ['db', 'push']
        );

        app(NotificationService::class)->sendToRole(
            'superadmin',
            'LESSON_COMPLETED',
            $adminPayload,
            ['db', 'push']
        );

        return response()->json([
            'success' => true,
            'is_read' => true
        ]);
    }
}
