<?php

return [

    /*
    |--------------------------------------------------------------------------
    | AI SUPPORT
    |--------------------------------------------------------------------------
    */

    'enabled' => env(
        'AI_SUPPORT_ENABLED',
        true
    ),

    'provider' => env(
        'AI_SUPPORT_PROVIDER',
        'openai'
    ),

    'name' => env(
        'AI_SUPPORT_NAME',
        'AVANTE-AI'
    ),

    /*
    |--------------------------------------------------------------------------
    | OPENAI
    |--------------------------------------------------------------------------
    */

    'openai' => [

        'api_key' => env(
            'OPENAI_API_KEY'
        ),

        'model' => env(
            'OPENAI_MODEL',
            'gpt-4o-mini'
        ),

    ],

    /*
    |--------------------------------------------------------------------------
    | CONTENT TRANSLATION
    |--------------------------------------------------------------------------
    |
    | Controls automatic translation of English content into
    | Hindi, Punjabi, etc. and saves translated text in database.
    |
    */

    'content_translation_enabled' => env(
        'OPENAI_CONTENT_TRANSLATION_ENABLED',
        false
    ),

    /*
    |--------------------------------------------------------------------------
    | MULTILANGUAGE AUDIO
    |--------------------------------------------------------------------------
    |
    | Controls non-English audio processing independently
    | from database content translation.
    |
    */

    'multilanguage_audio_enabled' => env(
        'OPENAI_MULTILANGUAGE_AUDIO_ENABLED',
        true
    ),

    /*
    |--------------------------------------------------------------------------
    | TEXT TO SPEECH
    |--------------------------------------------------------------------------
    */

    'tts_enabled' => env(
        'OPENAI_TTS_ENABLED',
        true
    ),

    'tts_model' => env(
        'OPENAI_TTS_MODEL',
        'gpt-4o-mini-tts'
    ),

    'tts_voice' => env(
        'OPENAI_TTS_VOICE',
        'alloy'
    ),

    /*
    |--------------------------------------------------------------------------
    | MAX AI CONTEXT
    |--------------------------------------------------------------------------
    */

    'max_context_chars' => env(
        'AI_SUPPORT_MAX_CONTEXT_CHARS',
        12000
    ),

    /*
    |--------------------------------------------------------------------------
    | AUTO REPLY
    |--------------------------------------------------------------------------
    */

    'auto_reply' => env(
        'AI_SUPPORT_AUTO_REPLY',
        true
    ),

];