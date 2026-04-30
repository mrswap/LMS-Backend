<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyDevice
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle($request, Closure $next)
    {
        $user = $request->user();

        if ($user && $user->device_id) {
            $deviceId = $request->header('X-Device-Id');

            if ($user->device_id !== $deviceId) {
                return response()->json([
                    'message' => 'Device mismatch. Session invalid.'
                ], 401);
            }
        }

        return $next($request);
    }
}
