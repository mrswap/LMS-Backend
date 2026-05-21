<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OpenAIService
{
    public function chat(array $messages): ?string
    {
        try {

            /*
            |------------------------------------------------------------------
            | LOG REQUEST
            |------------------------------------------------------------------
            */

            Log::channel('ai')->info(
                'OpenAI Request',
                [
                    'model' =>
                    config('ai.openai.model'),

                    'message_count' =>
                    count($messages),
                ]
            );

            /*
            |------------------------------------------------------------------
            | API REQUEST
            |------------------------------------------------------------------
            */

            $response = Http::timeout(120)

                ->retry(
                    3,
                    2000
                )

                ->withHeaders([

                    'Authorization' =>

                    'Bearer '
                        . config('ai.openai.api_key'),

                    'Content-Type' =>
                    'application/json',
                ])

                ->post(
                    'https://api.openai.com/v1/chat/completions',
                    [

                        'model' =>

                        config('ai.openai.model'),

                        'messages' => $messages,

                        'temperature' => 0.3,
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

            /*
            |------------------------------------------------------------------
            | LOG SUCCESS
            |------------------------------------------------------------------
            */

            Log::channel('ai')->info(
                'OpenAI Response Success',
                [
                    'status' =>
                    $response->status(),
                ]
            );

            /*
            |------------------------------------------------------------------
            | EXTRACT CONTENT
            |------------------------------------------------------------------
            */

            $content =
                $json['choices'][0]['message']['content']
                ?? null;

            if (! $content) {

                Log::channel('ai')->warning(
                    'OpenAI Empty Response',
                    [
                        'response' => $json,
                    ]
                );

                return null;
            }

            return trim($content);
        } catch (\Throwable $e) {

            /*
            |------------------------------------------------------------------
            | EXCEPTION
            |------------------------------------------------------------------
            */

            Log::channel('ai')->error(
                'OpenAI Error',
                [

                    'message' =>
                    $e->getMessage(),

                    'trace' =>
                    $e->getTraceAsString(),
                ]
            );

            return null;
        }
    }
}
