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

            $apiKey = config('ai.openai.api_key');
            $model = config('ai.openai.model');

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

            $response = Http::timeout(60)
                ->connectTimeout(20)
                ->retry(2, 2000)
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

            $content =
                $json['choices'][0]['message']['content']
                ?? null;

            if (! $content) {

                Log::channel('ai')->warning(
                    'OpenAI Empty Content',
                    [
                        'json' => $json,
                    ]
                );

                return null;
            }

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

    /*
    |--------------------------------------------------------------------------
    | TEXT TO SPEECH
    |--------------------------------------------------------------------------
    */

    public function speech(
        string $text,
        string $languageCode = 'en'
    ): ?string {
        try {

            $apiKey = config('ai.openai.api_key');

            $model = env(
                'OPENAI_TTS_MODEL',
                'gpt-4o-mini-tts'
            );

            $voice = env(
                'OPENAI_TTS_VOICE',
                'alloy'
            );

            $languageCode = strtolower(
                trim($languageCode)
            );

            /*
            |--------------------------------------------------------------------------
            | API KEY VALIDATION
            |--------------------------------------------------------------------------
            */

            if (! $apiKey) {

                Log::channel('ai')->error(
                    'OpenAI TTS API Key Missing'
                );

                return null;
            }

            /*
            |--------------------------------------------------------------------------
            | REQUEST LOG
            |--------------------------------------------------------------------------
            */

            Log::channel('ai')->info(
                'OpenAI TTS HTTP Request Started',
                [
                    'url' =>
                        'https://api.openai.com/v1/audio/speech',

                    'model' => $model,

                    'voice' => $voice,

                    'language' => $languageCode,

                    'text_length' => strlen($text),
                ]
            );

            /*
            |--------------------------------------------------------------------------
            | TTS REQUEST
            |--------------------------------------------------------------------------
            */

            $response = Http::timeout(120)
                ->connectTimeout(30)
                ->retry(2, 2000)
                ->withoutVerifying()
                ->withHeaders([
                    'Authorization' =>
                        'Bearer ' . $apiKey,

                    'Content-Type' =>
                        'application/json',

                    'Accept' =>
                        'audio/mpeg',
                ])
                ->post(
                    'https://api.openai.com/v1/audio/speech',
                    [
                        'model' => $model,

                        'voice' => $voice,

                        'input' => $text,

                        'response_format' => 'mp3',
                    ]
                );

            /*
            |--------------------------------------------------------------------------
            | RESPONSE LOG
            |--------------------------------------------------------------------------
            */

            Log::channel('ai')->info(
                'OpenAI TTS HTTP Response',
                [
                    'status' =>
                        $response->status(),

                    'successful' =>
                        $response->successful(),

                    'content_type' =>
                        $response->header('Content-Type'),

                    'body_size' =>
                        strlen($response->body()),
                ]
            );

            /*
            |--------------------------------------------------------------------------
            | ERROR
            |--------------------------------------------------------------------------
            */

            if (! $response->successful()) {

                Log::channel('ai')->error(
                    'OpenAI TTS HTTP Error',
                    [
                        'status' =>
                            $response->status(),

                        'reason' =>
                            $response->reason(),

                        'body' =>
                            $response->body(),
                    ]
                );

                return null;
            }

            $audioBinary = $response->body();

            if (! $audioBinary) {

                Log::channel('ai')->error(
                    'OpenAI TTS Empty Audio Response'
                );

                return null;
            }

            /*
            |--------------------------------------------------------------------------
            | SUCCESS
            |--------------------------------------------------------------------------
            */

            Log::channel('ai')->info(
                'OpenAI TTS Response Success',
                [
                    'language' =>
                        $languageCode,

                    'audio_size' =>
                        strlen($audioBinary),
                ]
            );

            return $audioBinary;

        } catch (ConnectionException $e) {

            Log::channel('ai')->error(
                'OpenAI TTS Connection Error',
                [
                    'language' =>
                        $languageCode,

                    'message' =>
                        $e->getMessage(),

                    'file' =>
                        $e->getFile(),

                    'line' =>
                        $e->getLine(),
                ]
            );

            return null;

        } catch (RequestException $e) {

            Log::channel('ai')->error(
                'OpenAI TTS Request Exception',
                [
                    'language' =>
                        $languageCode,

                    'message' =>
                        $e->getMessage(),

                    'file' =>
                        $e->getFile(),

                    'line' =>
                        $e->getLine(),
                ]
            );

            return null;

        } catch (\Throwable $e) {

            Log::channel('ai')->error(
                'OpenAI TTS Unknown Error',
                [
                    'language' =>
                        $languageCode,

                    'message' =>
                        $e->getMessage(),

                    'file' =>
                        $e->getFile(),

                    'line' =>
                        $e->getLine(),
                ]
            );

            return null;
        }
    }
}

