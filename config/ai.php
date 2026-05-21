<?php

return [

    'enabled' => env('AI_SUPPORT_ENABLED', true),
    'provider' => env('AI_SUPPORT_PROVIDER', 'openai'),
    'name' => env('AI_SUPPORT_NAME', 'AVANTE-AI'),
    'openai' => [
        'api_key' => env('OPENAI_API_KEY'),
        'model' => env('OPENAI_MODEL', 'gpt-5.5'),
    ],
    'max_context_chars' => env(
        'AI_SUPPORT_MAX_CONTEXT_CHARS',
        12000
    ),
    'auto_reply' => env(
        'AI_SUPPORT_AUTO_REPLY',
        true
    ),
];
