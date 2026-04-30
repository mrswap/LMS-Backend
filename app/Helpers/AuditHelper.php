<?php

use App\Models\AuditLog;

function audit_log($userId, $event, $desc = null, $meta = [])
{
    AuditLog::create([
        'user_id' => $userId,
        'event' => $event,
        'description' => $desc,
        'ip' => request()->ip(),
        'device' => request()->header('User-Agent'),
        'meta' => $meta
    ]);
}
