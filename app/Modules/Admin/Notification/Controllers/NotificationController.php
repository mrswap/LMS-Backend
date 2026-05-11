<?php

namespace App\Modules\Admin\Notification\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Notification;

class NotificationController extends Controller
{
    /*
    |------------------------------------------------------------------
    | 📥 GET NOTIFICATIONS
    |------------------------------------------------------------------
    */

    public function index(Request $request)
    {
        $notifications = $request->user()
            ->notifications()
            ->latest()
            ->paginate(20);

        return response()->json($notifications);
    }

    /*
    |------------------------------------------------------------------
    | 📌 MARK SINGLE READ
    |------------------------------------------------------------------
    */

    public function markRead($id)
    {
        $notification = Notification::where('id', $id)
            ->where('user_id', auth()->id())
            ->firstOrFail();

        $notification->markAsRead();

        $notification->refresh();

        return response()->json([
            'message' => 'Notification marked as read',
            'data' => $notification
        ]);
    }

    /*
    |------------------------------------------------------------------
    | 📌 MARK ALL READ
    |------------------------------------------------------------------
    */

    public function markAllRead(Request $request)
    {
        $request->user()
            ->notifications()
            ->where('is_read', false)
            ->update([
                'is_read' => true,
                'read_at' => now()
            ]);

        return response()->json([
            'message' => 'All notifications marked as read'
        ]);
    }

    /*
    |------------------------------------------------------------------
    | 🔢 UNREAD COUNT
    |------------------------------------------------------------------
    */

    public function unreadCount(Request $request)
    {
        $count = $request->user()
            ->notifications()
            ->where('is_read', false)
            ->count();

        return response()->json([
            'unread_count' => $count
        ]);
    }
}
