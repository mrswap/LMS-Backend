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
            | GLOBAL AI ENABLED?
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
                    ]
                );

                return null;
            }

            /*
            |--------------------------------------------------------------------------
            | USER QUESTION
            |--------------------------------------------------------------------------
            */

            $question = trim(
                $lastMessage->message ?? ''
            );

            if (! $question) {

                Log::channel('ai')->warning(
                    'Empty User Question',
                    [
                        'thread_id' => $thread->id,
                    ]
                );

                return null;
            }

            /*
            |--------------------------------------------------------------------------
            | TOPIC CONTEXT
            |--------------------------------------------------------------------------
            */

            $cache = app(
                TopicContextService::class
            )->cache($topic);

            Log::channel('ai')->info(
                'Topic Context Loaded',
                [
                    'topic_id' => $topic->id,
                    'context_length' =>
                    strlen($cache->context),
                ]
            );

            /*
            |--------------------------------------------------------------------------
            | SYSTEM PROMPT
            |--------------------------------------------------------------------------
            */

            $systemPrompt = "

You are AVANTE-AI.

You are an intelligent LMS learning assistant.

Your purpose is to help learners understand
their CURRENT TOPIC and CURRENT TRAINING CONTENT.

IMPORTANT RULES:

1. PRIORITIZE CURRENT TOPIC CONTENT.
2. Answer ONLY from provided training material.
3. Explain concepts in simple educational language.
4. Help learner understand the topic better.
5. If learner asks doubts,
   explain them clearly from context.
6. If learner greets casually,
   greet politely BUT still stay topic-focused.
7. Never invent medical information.
8. Never provide diagnosis or treatment advice.
9. Never answer outside LMS topic scope.
10. Keep answers educational, practical and easy.

If answer is unavailable in topic content,
say:

'Please contact your trainer/admin for further clarification.'

CURRENT LEARNING HIERARCHY:

Program:
{$topic->program?->title}

Level:
{$topic->level?->title}

Module:
{$topic->module?->title}

Chapter:
{$topic->chapter?->title}

Current Topic:
{$topic->title}

Topic Description:
{$topic->description}

TRAINING CONTENT:

" . substr(
                $cache->context,
                0,
                config('ai.max_context_chars')
            );

            /*
            |--------------------------------------------------------------------------
            | FINAL MESSAGES
            |--------------------------------------------------------------------------
            */

            $messages = [

                [
                    'role' => 'system',
                    'content' => $systemPrompt,
                ],

                [
                    'role' => 'user',
                    'content' => $question,
                ],
            ];

            Log::channel('ai')->info(
                'AI Final Prompt Prepared',
                [
                    'thread_id' => $thread->id,
                    'question' => $question,
                    'context_length' =>
                    strlen($systemPrompt),
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
            | EMPTY AI RESPONSE
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

                    'question' =>
                    $question,
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
            | SUCCESS
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
                        300
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
