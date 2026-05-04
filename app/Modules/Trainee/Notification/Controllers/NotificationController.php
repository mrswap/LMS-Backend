<?php

namespace App\Modules\Trainee\Notification\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Notification;

class NotificationController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | 📥 Get Notifications (Paginated)
    |--------------------------------------------------------------------------
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
    |--------------------------------------------------------------------------
    | 📌 Mark Single Notification as Read
    |--------------------------------------------------------------------------
    */
    public function markRead($id)
    {
        $notification = Notification::where('id', $id)
            ->where('user_id', auth()->id())
            ->firstOrFail();

        $notification->markAsRead();

        // fresh load (updated values ke saath)
        $notification->refresh();

        return response()->json([
            'message' => 'Notification marked as read',
            'data' => $notification
        ]);
    }
    /*
    |--------------------------------------------------------------------------
    | 📌 Mark All Notifications as Read
    |--------------------------------------------------------------------------
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
    |--------------------------------------------------------------------------
    | 🔢 Unread Count (for badge)
    |--------------------------------------------------------------------------
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
