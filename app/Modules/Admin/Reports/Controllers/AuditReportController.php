<?php

namespace App\Modules\Admin\Reports\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\AuditLog;

class AuditReportController extends Controller
{
    public function index(Request $request)
    {
        $query = AuditLog::query()
            ->with(['user:id,name,email']);

        /*
        |-----------------------------------------
        | 🔍 FILTERS
        |-----------------------------------------
        */

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

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
                $q->where('description', 'like', "%$search%")
                  ->orWhereHas('user', function ($u) use ($search) {
                      $u->where('name', 'like', "%$search%")
                        ->orWhere('email', 'like', "%$search%");
                  });
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
        | 🔥 TRANSFORM RESPONSE
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

                // ✅ Clean user data
                'user' => [
                    'id' => $log->user?->id,
                    'name' => $log->user?->name,
                    'email' => $log->user?->email,
                ],
            ];
        });

        /*
        |-----------------------------------------
        | 📤 FINAL RESPONSE
        |-----------------------------------------
        */
        return response()->json([
            'success' => true,
            'message' => 'Audit logs fetched successfully',
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