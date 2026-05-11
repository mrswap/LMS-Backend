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
        |--------------------------------------------------------------------------
        | 📘 READ TOGGLE
        |--------------------------------------------------------------------------
        */

        $progress = UserContentProgress::firstOrNew([
            'user_id' => $user->id,
            'topic_content_id' => $contentId
        ]);

        $progress->is_read = !$progress->is_read;

        $progress->read_at = $progress->is_read
            ? now()
            : null;

        $progress->save();

        /*
        |--------------------------------------------------------------------------
        | 🧾 AUDIT
        |--------------------------------------------------------------------------
        */

        AuditService::log(
            $progress->is_read
                ? 'lesson_completed'
                : 'lesson_unread',

            $progress->is_read
                ? 'User marked lesson as completed'
                : 'User removed lesson completed state',

            [
                'content_id' => $contentId,
                'user_id' => $user->id
            ]
        );

        /*
        |--------------------------------------------------------------------------
        | 🔔 NOTIFICATIONS
        |--------------------------------------------------------------------------
        */

        if ($progress->is_read) {

            /*
            |--------------------------------------------------------------------------
            | 👤 USER SELF
            |--------------------------------------------------------------------------
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
            |--------------------------------------------------------------------------
            | 🛡 ADMIN + SUPERADMIN
            |--------------------------------------------------------------------------
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
        }

        return response()->json([
            'success' => true,
            'is_read' => $progress->is_read
        ]);
    }
}
