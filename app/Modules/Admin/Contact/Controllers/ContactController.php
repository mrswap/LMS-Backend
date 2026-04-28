<?php

namespace App\Modules\Admin\Contact\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ContactMessage;

class ContactController extends Controller
{
    public function index(Request $request)
    {
        $perPage = $request->get('per_page', 10);
        $sortBy  = $request->get('sort_by', 'created_at');
        $order   = $request->get('order', 'desc');

        $query = ContactMessage::query();

        // 🔍 Search
        if ($request->filled('search')) {
            $search = $request->search;

            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%$search%")
                    ->orWhere('email', 'like', "%$search%")
                    ->orWhere('subject', 'like', "%$search%");
            });
        }

        // 🟢 Filter by seen/unseen
        if ($request->has('is_seen')) {
            $query->where('is_seen', $request->is_seen);
        }

        $data = $query
            ->orderBy($sortBy, $order)
            ->paginate($perPage);

        return response()->json([
            'status' => true,
            'data' => $data
        ]);
    }

    /*
    |--------------------------------------------------
    | MARK AS SEEN / UNSEEN
    |--------------------------------------------------
    */
    public function markSeen($id)
    {
        $message = ContactMessage::findOrFail($id);

        $message->is_seen = true;
        $message->save();

        return response()->json([
            'status' => true,
            'message' => 'Marked as seen'
        ]);
    }

    public function markUnseen($id)
    {
        $message = ContactMessage::findOrFail($id);

        $message->is_seen = false;
        $message->save();

        return response()->json([
            'status' => true,
            'message' => 'Marked as unseen'
        ]);
    }
}
