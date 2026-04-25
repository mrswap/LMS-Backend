<?php

namespace App\Modules\Trainee\Content\Controllers;

use App\Http\Controllers\Controller;
use App\Models\UserContentProgress;
use Illuminate\Http\Request;

class ContentProgressController extends Controller
{
    public function toggle($contentId)
    {
        $userId = auth()->id();

        $progress = UserContentProgress::firstOrNew([
            'user_id' => $userId,
            'topic_content_id' => $contentId
        ]);

        $progress->is_read = !$progress->is_read;
        $progress->read_at = $progress->is_read ? now() : null;
        $progress->save();

        return response()->json([
            'success' => true,
            'is_read' => $progress->is_read
        ]);
    }
}
