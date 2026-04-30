<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Support\Facades\Log;

class AuditService
{
    public static function log($event, $description = null, $meta = [])
    {
        try {
            $user = request()->user(); // ✅ FIX

            AuditLog::create([
                'user_id' => $user?->id,
                'event' => $event,
                'description' => $description,
                'ip' => request()->ip(),
                'device' => request()->header('User-Agent'),
                'meta' => $meta
            ]);
        } catch (\Exception $e) {
            Log::error('AUDIT ERROR: ' . $e->getMessage());
        }
    }
}
