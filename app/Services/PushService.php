<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PushService
{
    public function send($user, $content, $data = [])
    {
        $tokens = $user->devices()
            ->whereNotNull('fcm_token')
            ->pluck('fcm_token')
            ->toArray();

        if (empty($tokens)) {
            return;
        }

        foreach ($tokens as $token) {

            try {

                $response = Http::withHeaders([
                    'Authorization' => 'key=' . config('services.fcm.key'),
                    'Content-Type'  => 'application/json',
                ])->post('https://fcm.googleapis.com/fcm/send', [

                    'to' => $token,

                    'notification' => [
                        'title' => $content['title'],
                        'body'  => $content['message'],
                        'image' => $content['image'] ?? null,
                    ],

                    'data' => [
                        'type'   => $data['type'] ?? null,
                        'screen' => $data['screen'] ?? null,
                        'id'     => $data['id'] ?? null,
                        'extra'  => json_encode($data['extra'] ?? []),
                    ]
                ]);

                $result = $response->json();

                /*
                |--------------------------------------------------------------------------
                | ❌ Handle Invalid Tokens (VERY IMPORTANT)
                |--------------------------------------------------------------------------
                */
                if (isset($result['failure']) && $result['failure'] == 1) {

                    $error = $result['results'][0]['error'] ?? null;

                    if (in_array($error, ['NotRegistered', 'InvalidRegistration'])) {

                        // 🧹 Delete invalid token
                        $user->devices()
                            ->where('fcm_token', $token)
                            ->delete();
                    }
                }

            } catch (\Throwable $e) {

                Log::error('Push notification failed', [
                    'user_id' => $user->id ?? null,
                    'token'   => $token,
                    'error'   => $e->getMessage()
                ]);
            }
        }
    }
}