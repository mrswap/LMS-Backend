<?php

namespace App\Services\AI;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OpenAIService {
    /*
    |--------------------------------------------------------------------------
    | CHAT COMPLETION
    |--------------------------------------------------------------------------
    */

    public function chat(array $messages): ?string {
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

            $model = config(
                'ai.tts_model',
                'gpt-4o-mini-tts'
            );

            $voice = config(
                'ai.tts_voice',
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
        | EMPTY TEXT VALIDATION
        |--------------------------------------------------------------------------
        */

            $text = trim($text);

            if ($text === '') {

                Log::channel('ai')->warning(
                    'OpenAI TTS Empty Input'
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

                    'model' =>
                    $model,

                    'voice' =>
                    $voice,

                    'language' =>
                    $languageCode,

                    'text_length' =>
                    strlen($text),
                ]
            );

            /*
        |--------------------------------------------------------------------------
        | TTS REQUEST
        |--------------------------------------------------------------------------
        |
        | Do NOT use automatic retry here.
        |
        | 429 rate-limit should not immediately fire another request.
        |
        */

            $response = Http::timeout(120)
                ->connectTimeout(30)
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
                        'model' =>
                        $model,

                        'voice' =>
                        $voice,

                        'input' =>
                        $text,

                        'response_format' =>
                        'mp3',
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
        | RATE LIMIT
        |--------------------------------------------------------------------------
        */

            if ($response->status() === 429) {

                Log::channel('ai')->warning(
                    'OpenAI TTS Rate Limit Exceeded',
                    [
                        'language' =>
                        $languageCode,

                        'retry_after' =>
                        $response->header('Retry-After'),

                        'body' =>
                        $response->body(),
                    ]
                );

                return null;
            }

            /*
        |--------------------------------------------------------------------------
        | OTHER API ERROR
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

            /*
        |--------------------------------------------------------------------------
        | AUDIO RESPONSE
        |--------------------------------------------------------------------------
        */

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

    /*
    |--------------------------------------------------------------------------
    | CONTENT TRANSLATION
    |--------------------------------------------------------------------------
    */

    public function translate(
        string $text,
        string $targetLanguage
    ): ?string {

        try {

            $apiKey = config('ai.openai.api_key');
            $model = config(
                'ai.openai.model'
            );

            if (! $apiKey) {

                Log::channel('ai')->error(
                    'OpenAI Translation API Key Missing'
                );

                return null;
            }

            $text = trim($text);

            if ($text === '') {

                Log::channel('ai')->warning(
                    'OpenAI Translation Empty Input'
                );

                return null;
            }

            /*
        |--------------------------------------------------------------------------
        | LANGUAGE NAME
        |--------------------------------------------------------------------------
        */

            $language = match (strtolower(trim($targetLanguage))) {

                'hi' => 'Hindi',

                'pa' => 'Punjabi',

                'en' => 'English',

                default => $targetLanguage,
            };

            Log::channel('ai')->info(
                'OPENAI TRANSLATION REQUEST',
                [
                    'target_language' => $targetLanguage,
                    'language_name' => $language,
                    'text_length' => strlen($text),
                ]
            );

            /*
        |--------------------------------------------------------------------------
        | TRANSLATION PROMPT
        |--------------------------------------------------------------------------
        */

            $messages = [

                [
                    'role' => 'system',

                    'content' =>
                    'You are a professional LMS content translator. '
                        . 'Translate the supplied training content into the requested language. '
                        . 'Preserve the original meaning, structure, formatting, '
                        . 'HTML tags, line breaks, technical terms, numbers, '
                        . 'product names and proper nouns. '
                        . 'Do not add explanations, comments or extra text. '
                        . 'Return only the translated content.',
                ],

                [
                    'role' => 'user',

                    'content' =>
                    "Translate the following content into {$language}.\n\n"
                        . $text,
                ],

            ];

            /*
        |--------------------------------------------------------------------------
        | OPENAI REQUEST
        |--------------------------------------------------------------------------
        */

            $response = Http::timeout(120)
                ->connectTimeout(30)
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

                        'temperature' => 0.2,
                    ]
                );

            Log::channel('ai')->info(
                'OPENAI TRANSLATION RESPONSE',
                [
                    'status' =>
                    $response->status(),

                    'successful' =>
                    $response->successful(),

                    'target_language' =>
                    $targetLanguage,

                    'body_size' =>
                    strlen($response->body()),
                ]
            );

            if (! $response->successful()) {

                Log::channel('ai')->error(
                    'OPENAI TRANSLATION HTTP ERROR',
                    [
                        'status' =>
                        $response->status(),

                        'reason' =>
                        $response->reason(),

                        'body' =>
                        $response->body(),

                        'target_language' =>
                        $targetLanguage,
                    ]
                );

                return null;
            }

            $json = $response->json();

            $translated =
                $json['choices'][0]['message']['content']
                ?? null;

            if (! $translated) {

                Log::channel('ai')->warning(
                    'OPENAI TRANSLATION EMPTY RESPONSE',
                    [
                        'target_language' =>
                        $targetLanguage,

                        'json' =>
                        $json,
                    ]
                );

                return null;
            }

            $translated = trim($translated);

            Log::channel('ai')->info(
                'OPENAI TRANSLATION SUCCESS',
                [
                    'target_language' =>
                    $targetLanguage,

                    'translated_length' =>
                    strlen($translated),
                ]
            );

            return $translated;
        } catch (\Throwable $e) {

            Log::channel('ai')->error(
                'OPENAI TRANSLATION FAILED',
                [
                    'target_language' =>
                    $targetLanguage,

                    'message' =>
                    $e->getMessage(),

                    'line' =>
                    $e->getLine(),

                    'file' =>
                    $e->getFile(),
                ]
            );

            return null;
        }
    }
}
