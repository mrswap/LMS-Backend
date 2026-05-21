<?php

namespace App\Services\AI;

use App\Events\SupportMessageSent;
use App\Models\SupportMessage;
use App\Models\SupportThread;
use Illuminate\Support\Facades\Log;

class AiSupportService
{
    public function generateReply(
        SupportThread $thread
    ): ?SupportMessage {

        try {

            /*
            |--------------------------------------------------------------------------
            | START
            |--------------------------------------------------------------------------
            */

            Log::channel('ai')->info(
                'AI Reply Started',
                [
                    'thread_id' => $thread->id,
                ]
            );

            /*
            |--------------------------------------------------------------------------
            | AI ENABLED?
            |--------------------------------------------------------------------------
            */

            if (! config('ai.enabled')) {

                Log::channel('ai')->warning(
                    'AI Disabled Globally'
                );

                return null;
            }

            /*
            |--------------------------------------------------------------------------
            | THREAD AI ENABLED?
            |--------------------------------------------------------------------------
            */

            if (! $thread->ai_enabled) {

                Log::channel('ai')->warning(
                    'AI Disabled For Thread',
                    [
                        'thread_id' => $thread->id,
                    ]
                );

                return null;
            }

            /*
            |--------------------------------------------------------------------------
            | LOAD TOPIC
            |--------------------------------------------------------------------------
            */

            $topic = $thread->topic;

            if (! $topic) {

                Log::channel('ai')->warning(
                    'Thread Topic Missing',
                    [
                        'thread_id' => $thread->id,
                    ]
                );

                return null;
            }

            /*
            |--------------------------------------------------------------------------
            | LAST MESSAGE
            |--------------------------------------------------------------------------
            */

            $lastMessage = $thread->messages()
                ->latest()
                ->first();

            if (! $lastMessage) {

                Log::channel('ai')->warning(
                    'No Last Message Found',
                    [
                        'thread_id' => $thread->id,
                    ]
                );

                return null;
            }

            /*
            |--------------------------------------------------------------------------
            | SKIP AI SELF REPLY
            |--------------------------------------------------------------------------
            */

            if ($lastMessage->is_ai) {

                Log::channel('ai')->warning(
                    'Skipped AI Self Reply',
                    [
                        'thread_id' => $thread->id,
                        'message_id' => $lastMessage->id,
                    ]
                );

                return null;
            }

            /*
            |--------------------------------------------------------------------------
            | SKIP ADMIN MESSAGE REPLY
            |--------------------------------------------------------------------------
            */

            if ($lastMessage->is_admin) {

                Log::channel('ai')->warning(
                    'Skipped Admin Message AI Reply',
                    [
                        'thread_id' => $thread->id,
                        'message_id' => $lastMessage->id,
                    ]
                );

                return null;
            }

            /*
            |--------------------------------------------------------------------------
            | SKIP EMPTY MESSAGE
            |--------------------------------------------------------------------------
            */

            if (! trim($lastMessage->message ?? '')) {

                Log::channel('ai')->warning(
                    'Skipped Empty Message',
                    [
                        'thread_id' => $thread->id,
                        'message_id' => $lastMessage->id,
                    ]
                );

                return null;
            }

            /*
            |--------------------------------------------------------------------------
            | BUILD CONTEXT
            |--------------------------------------------------------------------------
            */

            $cache = app(
                TopicContextService::class
            )->cache($topic);

            /*
            |--------------------------------------------------------------------------
            | RECENT CONVERSATION
            |--------------------------------------------------------------------------
            */

            $recentMessages = $thread->messages()
                ->latest()
                ->take(10)
                ->get()
                ->reverse();

            $conversation = [];

            foreach ($recentMessages as $msg) {

                if (! trim($msg->message ?? '')) {
                    continue;
                }

                $conversation[] = [

                    'role' => (
                        $msg->is_admin
                        || $msg->is_ai
                    )
                        ? 'assistant'
                        : 'user',

                    'content' =>
                    trim($msg->message),
                ];
            }

            /*
            |--------------------------------------------------------------------------
            | SYSTEM PROMPT
            |--------------------------------------------------------------------------
            */

            $messages = [

                [
                    'role' => 'system',

                    'content' =>

                    "You are AVANTE-AI.

                    You are an internal LMS training assistant.

                    Rules:
                    - Only answer from provided training context.
                    - Never invent facts.
                    - Never provide diagnosis or treatment advice.
                    - Never answer beyond training material.
                    - If answer is not available,
                      ask learner to contact trainer/admin.
                    - Keep replies concise and educational.
                    - Use professional language.
                    - Answer in simple understandable format.
                    - If learner says hello/greetings,
                      greet politely.

                    TRAINING CONTEXT:

                    "

                    . substr(
                        $cache->context,
                        0,
                        config('ai.max_context_chars')
                    )
                ],
            ];

            /*
            |--------------------------------------------------------------------------
            | MERGE CHAT
            |--------------------------------------------------------------------------
            */

            $messages = array_merge(
                $messages,
                $conversation
            );

            Log::channel('ai')->info(
                'AI Prompt Prepared',
                [
                    'thread_id' => $thread->id,
                    'message_count' => count($messages),
                ]
            );

            /*
            |--------------------------------------------------------------------------
            | OPENAI REQUEST
            |--------------------------------------------------------------------------
            */

            $reply = app(
                OpenAIService::class
            )->chat($messages);

            /*
            |--------------------------------------------------------------------------
            | NO REPLY
            |--------------------------------------------------------------------------
            */

            if (! $reply) {

                Log::channel('ai')->warning(
                    'No AI Reply Generated',
                    [
                        'thread_id' => $thread->id,
                    ]
                );

                return null;
            }

            /*
            |--------------------------------------------------------------------------
            | SAVE MESSAGE
            |--------------------------------------------------------------------------
            */

            $message = SupportMessage::create([

                'thread_id' => $thread->id,

                'sender_id' => null,

                'message' => trim($reply),

                'is_admin' => false,

                'is_ai' => true,

                'ai_provider' =>
                config('ai.provider'),

                'ai_meta' => [

                    'model' =>
                    config('ai.openai.model'),

                    'generated_at' =>
                    now()->toDateTimeString(),

                    'topic_id' =>
                    $topic->id,
                ],
            ]);

            /*
            |--------------------------------------------------------------------------
            | UPDATE THREAD
            |--------------------------------------------------------------------------
            */

            $thread->update([

                'ai_last_reply_at' => now(),

                'last_message_at' => now(),
            ]);

            /*
            |--------------------------------------------------------------------------
            | BROADCAST
            |--------------------------------------------------------------------------
            */

            broadcast(
                new SupportMessageSent(
                    $message->load('sender')
                )
            );

            /*
            |--------------------------------------------------------------------------
            | SUCCESS LOG
            |--------------------------------------------------------------------------
            */

            Log::channel('ai')->info(
                'AI Reply Generated',
                [
                    'thread_id' => $thread->id,
                    'message_id' => $message->id,
                    'reply_preview' => substr(
                        $reply,
                        0,
                        150
                    ),
                ]
            );

            return $message;
        } catch (\Throwable $e) {

            /*
            |--------------------------------------------------------------------------
            | ERROR
            |--------------------------------------------------------------------------
            */

            Log::channel('ai')->error(
                'AI Support Error',
                [
                    'thread_id' => $thread->id ?? null,

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