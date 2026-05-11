<?php

namespace App\Services;

use App\Models\Setting;
use Google\Client;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PushService
{
    /*
    |--------------------------------------------------------------------------
    | 🚀 Send Push Notification
    |--------------------------------------------------------------------------
    */
    public function send($user, $content, $data = [])
    {
        Log::info('🚀 Push notification started', [
            'user_id' => $user->id ?? null,
            'type'    => $data['type'] ?? null,
        ]);

        /*
        |--------------------------------------------------------------------------
        | 🔎 Validate User
        |--------------------------------------------------------------------------
        */
        if (!$user) {

            Log::error('❌ Push failed: user missing');

            return;
        }

        /*
        |--------------------------------------------------------------------------
        | 📱 Get User Tokens
        |--------------------------------------------------------------------------
        */
        $tokens = $user->devices()
            ->whereNotNull('fcm_token')
            ->pluck('fcm_token')
            ->filter()
            ->unique()
            ->values()
            ->toArray();

        Log::info('📱 Device tokens fetched', [
            'user_id' => $user->id,
            'count'   => count($tokens),
            'tokens'  => $tokens,
        ]);

        if (empty($tokens)) {

            Log::warning('⚠ No FCM tokens found', [
                'user_id' => $user->id,
            ]);

            return;
        }

        /*
        |--------------------------------------------------------------------------
        | 🔥 Firebase Config
        |--------------------------------------------------------------------------
        */
        $firebase = Setting::getFirebaseConfig();

        Log::info('🔥 Firebase config loaded', [
            'exists'            => !!$firebase,
            'project_id'        => $firebase['project_id'] ?? null,
            'client_email'      => $firebase['client_email'] ?? null,
            'has_private_key'   => !empty($firebase['private_key']),
            'has_client_id'     => !empty($firebase['client_id']),
        ]);

        if (!$firebase) {

            Log::error('❌ Firebase config missing');

            return;
        }

        /*
        |--------------------------------------------------------------------------
        | 🔑 Access Token
        |--------------------------------------------------------------------------
        */
        $accessToken = $this->getAccessToken($firebase);

        Log::info('🔐 Access token result', [
            'success' => !!$accessToken,
            'preview' => $accessToken
                ? substr($accessToken, 0, 25) . '...'
                : null,
        ]);

        if (!$accessToken) {

            Log::error('❌ Unable to generate FCM access token');

            return;
        }

        /*
        |--------------------------------------------------------------------------
        | 📤 Send To Each Device
        |--------------------------------------------------------------------------
        */
        foreach ($tokens as $token) {

            try {

                /*
                |--------------------------------------------------------------------------
                | ⚠ Detect Expo Token
                |--------------------------------------------------------------------------
                */
                if (str_starts_with($token, 'ExponentPushToken')) {

                    Log::warning('⚠ Expo token detected (not FCM token)', [
                        'user_id' => $user->id,
                        'token'   => $token,
                    ]);

                    continue;
                }

                $payload = [
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

                        'android' => [
                            'priority' => 'high',
                            'notification' => [
                                'sound' => 'default',
                            ],
                        ],

                        'apns' => [
                            'payload' => [
                                'aps' => [
                                    'sound' => 'default',
                                ],
                            ],
                        ],
                    ]
                ];

                Log::info('📤 Sending FCM request', [
                    'user_id' => $user->id,
                    'token'   => $token,
                    'url'     => "https://fcm.googleapis.com/v1/projects/{$firebase['project_id']}/messages:send",
                ]);

                $response = Http::timeout(15)
                    ->withHeaders([
                        'Authorization' => 'Bearer ' . $accessToken,
                        'Content-Type'  => 'application/json',
                    ])
                    ->post(
                        "https://fcm.googleapis.com/v1/projects/{$firebase['project_id']}/messages:send",
                        $payload
                    );

                Log::info('📩 FCM response', [
                    'user_id' => $user->id,
                    'token'   => $token,
                    'status'  => $response->status(),
                    'body'    => $response->body(),
                ]);

                /*
                |--------------------------------------------------------------------------
                | ❌ Failed Response
                |--------------------------------------------------------------------------
                */
                if (!$response->successful()) {

                    $result = $response->json();

                    $errorCode = $result['error']['status'] ?? null;

                    Log::error('❌ FCM push failed', [
                        'user_id'   => $user->id,
                        'token'     => $token,
                        'status'    => $response->status(),
                        'errorCode' => $errorCode,
                        'response'  => $result,
                    ]);

                    /*
                    |--------------------------------------------------------------------------
                    | 🧹 Remove Invalid Tokens
                    |--------------------------------------------------------------------------
                    */
                    if (in_array($errorCode, [
                        'NOT_FOUND',
                        'INVALID_ARGUMENT',
                        'UNREGISTERED',
                    ])) {

                        Log::warning('🧹 Removing invalid token', [
                            'user_id' => $user->id,
                            'token'   => $token,
                        ]);

                        $user->devices()
                            ->where('fcm_token', $token)
                            ->delete();
                    }

                    continue;
                }

                Log::info('✅ Push notification sent successfully', [
                    'user_id' => $user->id,
                    'token'   => $token,
                ]);
            } catch (\Throwable $e) {

                Log::error('💥 Push send exception', [
                    'user_id' => $user->id ?? null,
                    'token'   => $token,
                    'message' => $e->getMessage(),
                    'file'    => $e->getFile(),
                    'line'    => $e->getLine(),
                ]);
            }
        }

        Log::info('🏁 Push notification process completed', [
            'user_id' => $user->id,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | 🔐 Generate Firebase Access Token
    |--------------------------------------------------------------------------
    */
    private function getAccessToken(array $firebase)
    {
        /*
        |--------------------------------------------------------------------------
        | ⚠ Clear Old Broken Cache Automatically
        |--------------------------------------------------------------------------
        */
        cache()->forget('fcm_access_token');

        return cache()->remember(
            'fcm_access_token',
            now()->addMinutes(50),
            function () use ($firebase) {

                try {

                    Log::info('🔑 Starting Firebase access token generation');

                    /*
                    |--------------------------------------------------------------------------
                    | ✅ Validate Config
                    |--------------------------------------------------------------------------
                    */
                    $required = [
                        'type',
                        'project_id',
                        'private_key',
                        'client_email',
                        'client_id',
                        'token_uri',
                    ];

                    foreach ($required as $field) {

                        if (empty($firebase[$field])) {

                            Log::error('❌ Firebase config missing field', [
                                'field' => $field,
                            ]);

                            return null;
                        }
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | 🔧 Fix Multiline Private Key
                    |--------------------------------------------------------------------------
                    */
                    $firebase['private_key'] = str_replace(
                        '\n',
                        "\n",
                        $firebase['private_key']
                    );

                    /*
                    |--------------------------------------------------------------------------
                    | 🚀 Google Client
                    |--------------------------------------------------------------------------
                    */
                    $client = new Client();

                    $client->setAuthConfig($firebase);

                    $client->addScope(
                        'https://www.googleapis.com/auth/firebase.messaging'
                    );

                    /*
                    |--------------------------------------------------------------------------
                    | 🔑 Generate Token
                    |--------------------------------------------------------------------------
                    */
                    $token = $client->fetchAccessTokenWithAssertion();

                    Log::info('✅ Firebase access token generated', [
                        'has_access_token' => isset($token['access_token']),
                        'expires_in'       => $token['expires_in'] ?? null,
                    ]);

                    return $token['access_token'] ?? null;
                } catch (\Throwable $e) {

                    Log::error('💥 Firebase token generation failed', [
                        'message' => $e->getMessage(),
                        'file'    => $e->getFile(),
                        'line'    => $e->getLine(),
                        'trace'   => $e->getTraceAsString(),
                    ]);

                    return null;
                }
            }
        );
    }
}
