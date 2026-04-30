<?php

namespace App\Modules\Trainee\Content\Controllers;

use App\Http\Controllers\Controller;
use App\Models\UserContentProgress;
use Illuminate\Http\Request;
use App\Services\AuditService;


class ContentProgressController extends Controller
{
    public function toggle($contentId)
    {
        AuditService::log('lesson_toggled', 'User toggled read status for a content item', ['content_id' => $contentId]);

        $userId = auth()->id();

        $progress = UserContentProgress::firstOrNew([
            'user_id' => $userId,
            'topic_content_id' => $contentId
        ]);

        AuditService::log('lesson_toggled', 'User toggled read status for a content item', ['content_id' => $contentId]);

        $progress->is_read = !$progress->is_read;
        $progress->read_at = $progress->is_read ? now() : null;
        $progress->save();

        return response()->json([
            'success' => true,
            'is_read' => $progress->is_read
        ]);
    }
}
