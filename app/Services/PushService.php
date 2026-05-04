<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\Setting;
use Google\Client;

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

        $firebase = Setting::getFirebaseConfig();

        if (!$firebase) {
            Log::error('Firebase config missing');
            return;
        }

        // 🔥 Generate once (not per token)
        $accessToken = $this->getAccessToken($firebase);

        if (!$accessToken) {
            Log::error('FCM access token generation failed');
            return;
        }

        foreach ($tokens as $token) {

            try {

                $response = Http::timeout(10)
                    ->withHeaders([
                        'Authorization' => 'Bearer ' . $accessToken,
                        'Content-Type'  => 'application/json',
                    ])
                    ->post(
                        "https://fcm.googleapis.com/v1/projects/{$firebase['project_id']}/messages:send",
                        [
                            'message' => [
                                'token' => $token,

                                'notification' => [
                                    'title' => $content['title'] ?? 'Notification',
                                    'body'  => $content['message'] ?? '',
                                ],

                                'data' => [
                                    'type'   => (string) ($data['type'] ?? ''),
                                    'screen' => (string) ($data['screen'] ?? ''),
                                    'id'     => (string) ($data['id'] ?? ''),
                                    'extra'  => json_encode($data['extra'] ?? []),
                                ],
                            ]
                        ]
                    );

                /*
                |--------------------------------------------------------------------------
                | ❌ Handle Errors Properly
                |--------------------------------------------------------------------------
                */
                if (!$response->successful()) {

                    Log::error('FCM push failed', [
                        'user_id' => $user->id,
                        'token'   => $token,
                        'status'  => $response->status(),
                        'body'    => $response->body()
                    ]);

                    $result = $response->json();

                    $errorCode = $result['error']['status'] ?? null;

                    if (in_array($errorCode, ['NOT_FOUND', 'INVALID_ARGUMENT'])) {

                        // 🧹 remove invalid token
                        $user->devices()
                            ->where('fcm_token', $token)
                            ->delete();
                    }
                }
            } catch (\Throwable $e) {

                Log::error('Push exception', [
                    'user_id' => $user->id ?? null,
                    'token'   => $token,
                    'error'   => $e->getMessage()
                ]);
            }
        }
    }

    /*
    |--------------------------------------------------------------------------
    | 🔐 Generate Access Token (Cached)
    |--------------------------------------------------------------------------
    */
    private function getAccessToken($firebase)
    {
        return cache()->remember('fcm_access_token', 3000, function () use ($firebase) {

            try {
                $client = new Client();

                $client->setAuthConfig([
                    'type'         => 'service_account',
                    'project_id'   => $firebase['project_id'],
                    'private_key'  => $firebase['private_key'],
                    'client_email' => $firebase['client_email'],
                ]);

                $client->addScope('https://www.googleapis.com/auth/firebase.messaging');

                $token = $client->fetchAccessTokenWithAssertion();

                return $token['access_token'] ?? null;
            } catch (\Throwable $e) {

                Log::error('FCM token error', [
                    'error' => $e->getMessage()
                ]);

                return null;
            }
        });
    }
}
