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

            Log::channel('ai')->info(
                'AI Reply Started',
                [
                    'thread_id' => $thread->id,
                ]
            );

            /*
            |------------------------------------------------------------------
            | AI ENABLED?
            |------------------------------------------------------------------
            */

            if (! config('ai.enabled')) {

                Log::channel('ai')->warning(
                    'AI Disabled'
                );

                return null;
            }

            /*
            |------------------------------------------------------------------
            | THREAD AI ENABLED?
            |------------------------------------------------------------------
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
            |------------------------------------------------------------------
            | TOPIC EXISTS?
            |------------------------------------------------------------------
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
            |------------------------------------------------------------------
            | PREVENT AI SELF LOOP
            |------------------------------------------------------------------
            */

            $lastMessage = $thread->messages()
                ->latest()
                ->first();

            if ($lastMessage?->is_ai) {

                Log::channel('ai')->warning(
                    'Skipped AI Self Reply',
                    [
                        'thread_id' => $thread->id,
                    ]
                );

                return null;
            }

            /*
            |------------------------------------------------------------------
            | BUILD / CACHE TOPIC CONTEXT
            |------------------------------------------------------------------
            */

            $cache = app(
                TopicContextService::class
            )->cache($topic);

            /*
            |------------------------------------------------------------------
            | RECENT CONVERSATION
            |------------------------------------------------------------------
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

                    'content' => trim(
                        $msg->message
                    ),
                ];
            }

            /*
            |------------------------------------------------------------------
            | OPENAI MESSAGES
            |------------------------------------------------------------------
            */

            $messages = [

                [

                    'role' => 'system',

                    'content' =>

                    "You are AVANTE-AI.

                            You are an internal LMS training assistant.

                            Rules:
                            - Only answer from provided training context.
                            - Never invent medical facts.
                            - Never provide diagnosis or treatment advice.
                            - Never answer beyond training material.
                            - If answer is not available,
                            ask learner to contact trainer/admin.
                            - Keep replies concise and educational.
                            - Use professional language.

                            TRAINING CONTEXT:

                            "

                        . substr(

                            $cache->context,

                            0,

                            config(
                                'ai.max_context_chars'
                            )
                        ),
                ],
            ];

            $messages = array_merge(
                $messages,
                $conversation
            );

            /*
            |------------------------------------------------------------------
            | GENERATE AI RESPONSE
            |------------------------------------------------------------------
            */

            $reply = app(
                OpenAIService::class
            )->chat($messages);

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
            |------------------------------------------------------------------
            | CREATE AI MESSAGE
            |------------------------------------------------------------------
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
                ],
            ]);

            /*
            |------------------------------------------------------------------
            | UPDATE THREAD
            |------------------------------------------------------------------
            */

            $thread->update([

                'ai_last_reply_at' => now(),

                'last_message_at' => now(),
            ]);

            /*
            |------------------------------------------------------------------
            | BROADCAST
            |------------------------------------------------------------------
            */

            broadcast(
                new SupportMessageSent(
                    $message
                )
            );

            /*
            |------------------------------------------------------------------
            | LOG SUCCESS
            |------------------------------------------------------------------
            */

            Log::channel('ai')->info(
                'AI Reply Generated',
                [
                    'thread_id' => $thread->id,
                    'message_id' => $message->id,
                ]
            );

            return $message;
        } catch (\Throwable $e) {

            Log::channel('ai')->error(
                'AI Support Error',
                [
                    'thread_id' => $thread->id,
                    'message' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]
            );

            return null;
        }
    }
}
