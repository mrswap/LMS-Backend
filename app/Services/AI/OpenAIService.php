<?php

namespace App\Services\AI;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OpenAIService
{
    /*
    |--------------------------------------------------------------------------
    | CHAT COMPLETION
    |--------------------------------------------------------------------------
    */

    public function chat(array $messages): ?string
    {
        try {

            /*
            |------------------------------------------------------------------
            | CONFIG
            |------------------------------------------------------------------
            */

            $apiKey = config('ai.openai.api_key');

            $model = config('ai.openai.model');

            /*
            |------------------------------------------------------------------
            | VALIDATION
            |------------------------------------------------------------------
            */

            if (! $apiKey) {

                Log::channel('ai')->error(
                    'OpenAI API Key Missing'
                );

                return null;
            }

            if (! $model) {

                Log::channel('ai')->error(
                    'OpenAI Model Missing'
                );

                return null;
            }

            /*
            |------------------------------------------------------------------
            | LOG REQUEST
            |------------------------------------------------------------------
            */

            Log::channel('ai')->info(
                'OpenAI Request Started',
                [

                    'url' =>

                    'https://api.openai.com/v1/chat/completions',

                    'model' => $model,

                    'message_count' => count($messages),

                    'messages_preview' => collect($messages)
                        ->map(function ($msg) {

                            return [

                                'role' =>
                                $msg['role'] ?? null,

                                'content_preview' =>
                                mb_substr(
                                    $msg['content'] ?? '',
                                    0,
                                    300
                                ),
                            ];
                        }),

                    'php_version' => PHP_VERSION,

                    'curl_enabled' =>
                    extension_loaded('curl'),

                    'openssl_enabled' =>
                    extension_loaded('openssl'),
                ]
            );

            /*
            |------------------------------------------------------------------
            | HTTP REQUEST
            |------------------------------------------------------------------
            */

            $response = Http::timeout(60)

                ->connectTimeout(20)

                ->retry(
                    2,
                    2000
                )

                /*
                |--------------------------------------------------------------
                | TEMP DEBUG SSL
                |--------------------------------------------------------------
                */

                ->withoutVerifying()

                ->withHeaders([

                    'Authorization' =>

                    'Bearer ' . $apiKey,

                    'Content-Type' =>
                    'application/json',
                ])

                ->post(
                    'https://api.openai.com/v1/chat/completions',
                    [

                        'model' => $model,

                        'messages' => $messages,

                        'temperature' => 0.3,
                    ]
                );

            /*
            |------------------------------------------------------------------
            | RAW RESPONSE LOG
            |------------------------------------------------------------------
            */

            Log::channel('ai')->info(
                'OpenAI Raw Response',
                [

                    'status' =>
                    $response->status(),

                    'successful' =>
                    $response->successful(),

                    'failed' =>
                    $response->failed(),

                    'headers' =>
                    $response->headers(),

                    'body' =>
                    $response->body(),
                ]
            );

            /*
            |------------------------------------------------------------------
            | FAILED RESPONSE
            |------------------------------------------------------------------
            */

            if (! $response->successful()) {

                Log::channel('ai')->error(
                    'OpenAI HTTP Error',
                    [

                        'status' =>
                        $response->status(),

                        'reason' =>
                        $response->reason(),

                        'headers' =>
                        $response->headers(),

                        'body' =>
                        $response->body(),
                    ]
                );

                return null;
            }

            /*
            |------------------------------------------------------------------
            | RESPONSE JSON
            |------------------------------------------------------------------
            */

            $json = $response->json();

            Log::channel('ai')->info(
                'OpenAI JSON Parsed',
                [

                    'has_choices' =>
                    isset($json['choices']),

                    'choices_count' =>
                    count($json['choices'] ?? []),

                    'usage' =>
                    $json['usage'] ?? null,
                ]
            );

            /*
            |------------------------------------------------------------------
            | CONTENT
            |------------------------------------------------------------------
            */

            $content =
                $json['choices'][0]['message']['content']
                ?? null;

            /*
            |------------------------------------------------------------------
            | EMPTY CONTENT
            |------------------------------------------------------------------
            */

            if (! $content) {

                Log::channel('ai')->warning(
                    'OpenAI Empty Content',
                    [
                        'json' => $json,
                    ]
                );

                return null;
            }

            /*
            |------------------------------------------------------------------
            | SUCCESS
            |------------------------------------------------------------------
            */

            Log::channel('ai')->info(
                'OpenAI Response Success',
                [

                    'response_length' =>
                    strlen($content),

                    'preview' =>
                    mb_substr(
                        $content,
                        0,
                        500
                    ),
                ]
            );

            return trim($content);

        } catch (ConnectionException $e) {

            /*
            |------------------------------------------------------------------
            | CONNECTION ERROR
            |------------------------------------------------------------------
            */

            Log::channel('ai')->error(
                'OpenAI Connection Error',
                [

                    'message' =>
                    $e->getMessage(),

                    'file' =>
                    $e->getFile(),

                    'line' =>
                    $e->getLine(),

                    'trace' =>
                    $e->getTraceAsString(),
                ]
            );

            return null;

        } catch (RequestException $e) {

            /*
            |------------------------------------------------------------------
            | REQUEST ERROR
            |------------------------------------------------------------------
            */

            Log::channel('ai')->error(
                'OpenAI Request Exception',
                [

                    'message' =>
                    $e->getMessage(),

                    'file' =>
                    $e->getFile(),

                    'line' =>
                    $e->getLine(),

                    'trace' =>
                    $e->getTraceAsString(),
                ]
            );

            return null;

        } catch (\Throwable $e) {

            /*
            |------------------------------------------------------------------
            | UNKNOWN ERROR
            |------------------------------------------------------------------
            */

            Log::channel('ai')->error(
                'OpenAI Unknown Error',
                [

                    'message' =>
                    $e->getMessage(),

                    'file' =>
                    $e->getFile(),

                    'line' =>
                    $e->getLine(),

                    'trace' =>
                    $e->getTraceAsString(),
                ]
            );

            return null;
        }
    }
}