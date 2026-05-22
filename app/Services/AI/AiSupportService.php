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
        |--------------------------------------------------------------
        | AI ENABLED?
        |--------------------------------------------------------------
        */

            if (! config('ai.enabled')) {

                return null;
            }

            if (! $thread->ai_enabled) {

                return null;
            }

            /*
        |--------------------------------------------------------------
        | LOAD TOPIC
        |--------------------------------------------------------------
        */

            $thread->loadMissing([
                'topic',
            ]);

            $topic = $thread->topic;

            if (! $topic) {

                Log::channel('ai')->warning(
                    'Topic Missing',
                    [
                        'thread_id' => $thread->id,
                    ]
                );

                return null;
            }

            /*
        |--------------------------------------------------------------
        | BUILD CONTEXT
        |--------------------------------------------------------------
        */

            $cache = app(
                TopicContextService::class
            )->cache($topic);

            /*
        |--------------------------------------------------------------
        | LATEST LEARNER MESSAGE
        |--------------------------------------------------------------
        */

            $latestLearnerMessage = $thread->messages()

                ->where('is_ai', false)

                ->where('is_admin', false)

                ->latest()

                ->first();

            if (! $latestLearnerMessage) {

                return null;
            }

            /*
        |--------------------------------------------------------------
        | PREVENT AI SELF LOOP
        |--------------------------------------------------------------
        */

            $lastMessage = $thread->messages()
                ->latest()
                ->first();

            if ($lastMessage?->is_ai) {

                Log::channel('ai')->warning(
                    'AI Self Loop Prevented',
                    [
                        'thread_id' => $thread->id,
                    ]
                );

                return null;
            }

            /*
        |--------------------------------------------------------------
        | RECENT CONVERSATION
        |--------------------------------------------------------------
        */

            $recentMessages = $thread->messages()

                ->where(function ($q) {

                    $q

                        ->whereNotNull('message')

                        ->where('message', '!=', '');
                })

                ->latest()

                ->take(8)

                ->get()

                ->reverse();

            $conversation = [];

            foreach ($recentMessages as $msg) {

                $text = trim($msg->message);

                if (! $text) {
                    continue;
                }

                /*
            |----------------------------------------------------------
            | SKIP GENERIC GREETINGS
            |----------------------------------------------------------
            */

                $lower = strtolower($text);

                if (

                    in_array($lower, [

                        'hi',
                        'hello',
                        'hey',
                        'hy',
                        'ok',
                        'thanks',
                    ])

                ) {
                    continue;
                }

                /*
            |----------------------------------------------------------
            | AI MESSAGE
            |----------------------------------------------------------
            */

                if ($msg->is_ai) {

                    $conversation[] = [

                        'role' => 'assistant',

                        'content' => $text,
                    ];

                    continue;
                }

                /*
            |----------------------------------------------------------
            | ADMIN MESSAGE
            |----------------------------------------------------------
            */

                if ($msg->is_admin) {

                    $conversation[] = [

                        'role' => 'system',

                        'content' =>

                        "Trainer Guidance:\n"
                            . $text,
                    ];

                    continue;
                }

                /*
            |----------------------------------------------------------
            | USER MESSAGE
            |----------------------------------------------------------
            */

                $conversation[] = [

                    'role' => 'user',

                    'content' => $text,
                ];
            }

            /*
        |--------------------------------------------------------------
        | SYSTEM PROMPT
        |--------------------------------------------------------------
        */

            $systemPrompt = "

                            You are AVANTE-AI.

                            You are an expert LMS medical learning assistant.

                            You help learners understand the CURRENT TOPIC they are studying.

                            IMPORTANT BEHAVIOR:

                            - You MUST answer learner questions directly.
                            - You MUST explain concepts from LMS content.
                            - You MUST behave like a trainer/tutor.
                            - DO NOT repeatedly greet the learner.
                            - DO NOT say:
                            'How may I assist you today?'
                            - DO NOT act like customer support.
                            - Focus on education and explanation.

                            VERY IMPORTANT:

                            The learner is currently studying THIS TOPIC:

                            {$topic->title}

                            Use ONLY the LMS training context below.

                            If learner asks:
                            - what is
                            - explain
                            - types
                            - why
                            - how
                            - difference
                            - symptoms
                            - function
                            - mechanism

                            Then explain using LMS content naturally.

                            If answer is unavailable in LMS content:
                            Say:
                            'Please contact trainer/admin for more clarification.'

                            CURRENT LMS TRAINING CONTENT:

                            "

                . substr(
                    $cache->context,
                    0,
                    config('ai.max_context_chars')
                );

            /*
        |--------------------------------------------------------------
        | FINAL USER QUESTION PRIORITY
        |--------------------------------------------------------------
        */

            $conversation[] = [

                'role' => 'system',

                'content' =>

                "The learner's MOST IMPORTANT current question is:

                {$latestLearnerMessage->message}

                Prioritize answering this question.",
            ];

            /*
        |--------------------------------------------------------------
        | FINAL PAYLOAD
        |--------------------------------------------------------------
        */

            $messages = [

                [
                    'role' => 'system',

                    'content' => $systemPrompt,
                ],
            ];

            $messages = array_merge(
                $messages,
                $conversation
            );

            /*
        |--------------------------------------------------------------
        | DEBUG LOG
        |--------------------------------------------------------------
        */

            Log::channel('ai')->info(
                'Final OpenAI Payload',
                [
                    'thread_id' => $thread->id,
                    'messages' => $messages,
                ]
            );

            /*
        |--------------------------------------------------------------
        | OPENAI REQUEST
        |--------------------------------------------------------------
        */

            $reply = app(
                OpenAIService::class
            )->chat($messages);

            if (! $reply) {

                Log::channel('ai')->warning(
                    'AI Empty Reply',
                    [
                        'thread_id' => $thread->id,
                    ]
                );

                return null;
            }

            /*
        |--------------------------------------------------------------
        | SAVE MESSAGE
        |--------------------------------------------------------------
        */

            $message = SupportMessage::create([

                'thread_id' => $thread->id,

                'sender_id' => null,

                'message' => trim($reply),

                'attachment' => null,

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
        |--------------------------------------------------------------
        | UPDATE THREAD
        |--------------------------------------------------------------
        */

            $thread->update([

                'ai_last_reply_at' => now(),

                'last_message_at' => now(),
            ]);

            /*
        |--------------------------------------------------------------
        | BROADCAST
        |--------------------------------------------------------------
        */

            broadcast(
                new SupportMessageSent(
                    $message
                )
            )->toOthers();

            Log::channel('ai')->info(
                'AI Reply Generated',
                [
                    'thread_id' => $thread->id,
                    'reply' => $reply,
                ]
            );

            return $message;
        } catch (\Throwable $e) {

            Log::channel('ai')->error(
                'AI Support Error',
                [
                    'thread_id' => $thread->id ?? null,
                    'message' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]
            );

            return null;
        }
    }
}
