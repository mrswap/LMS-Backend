<?php

namespace App\Modules\Trainee\Reports\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\AuditLog;

class AuditReportController extends Controller
{
    public function index(Request $request)
    {
        $userId = auth()->id();

        $query = AuditLog::query()
            ->where('user_id', $userId) // 🔥 ONLY CURRENT USER
            ->with(['user:id,name,email']);

        /*
        |-----------------------------------------
        | 🔍 FILTERS (same as admin)
        |-----------------------------------------
        */

        if ($request->filled('event')) {
            $query->where('event', $request->event);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        if ($request->filled('search')) {
            $search = $request->search;

            $query->where(function ($q) use ($search) {
                $q->where('description', 'like', "%$search%");
            });
        }

        /*
        |-----------------------------------------
        | 🔽 SORTING
        |-----------------------------------------
        */
        $sortBy = $request->get('sort_by', 'id');
        $sortOrder = $request->get('sort_order', 'desc');

        $query->orderBy($sortBy, $sortOrder);

        /*
        |-----------------------------------------
        | 📄 PAGINATION
        |-----------------------------------------
        */
        $perPage = $request->get('per_page', 10);

        $logs = $query->paginate($perPage);

        /*
        |-----------------------------------------
        | 🔥 TRANSFORM
        |-----------------------------------------
        */
        $data = collect($logs->items())->map(function ($log) {
            return [
                'id' => $log->id,
                'event' => $log->event,
                'description' => $log->description,
                'ip' => $log->ip,
                'device' => $log->device,
                'created_at' => $log->created_at,
            ];
        });

        return response()->json([
            'success' => true,
            'message' => 'Your audit logs fetched successfully',
            'data' => $data,
            'meta' => [
                'current_page' => $logs->currentPage(),
                'last_page' => $logs->lastPage(),
                'per_page' => $logs->perPage(),
                'total' => $logs->total(),
            ]
        ]);
    }
}
