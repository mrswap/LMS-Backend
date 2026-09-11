<?php

namespace App\Models;

use Illuminate\Support\Facades\Log;
use App\Jobs\GenerateTopicContentAudioJob;

class TopicContentTranslation extends BaseModel
{
    protected $fillable = [
        'topic_content_id',
        'language_code',
        'title',
        'content',

        // TTS
        'audio_path',
        'audio_generated_at',
        'audio_provider',
    ];

    protected $casts = [
        'audio_generated_at' => 'datetime',
    ];

    protected $appends = [
        'audio_url',
    ];

    /*
    |--------------------------------------------------------------------------
    | MODEL EVENTS
    |--------------------------------------------------------------------------
    */

    protected static function booted()
    {
        parent::booted();

        /*
        |--------------------------------------------------------------------------
        | TRANSLATION CREATED
        |--------------------------------------------------------------------------
        */

        static::created(function ($translation) {

            if (
                ! config('ai.tts_enabled', true)
                || empty($translation->content)
            ) {
                return;
            }

            $topicContent = $translation->topicContent;

            /*
            |--------------------------------------------------------------------------
            | Only TEXT content
            |--------------------------------------------------------------------------
            */

            if (
                ! $topicContent
                || $topicContent->type !== 'text'
            ) {
                return;
            }

            $language = strtolower(
                trim($translation->language_code)
            );

            /*
            |--------------------------------------------------------------------------
            | English handled by TopicContent
            |--------------------------------------------------------------------------
            */

            if ($language === 'en') {
                return;
            }

            Log::channel('ai')->info(
                'TRANSLATION TTS CREATE - DISPATCHING JOB',
                [
                    'content_id' => $translation->topic_content_id,
                    'translation_id' => $translation->id,
                    'language' => $language,
                ]
            );

            GenerateTopicContentAudioJob::dispatch(
                $translation->topic_content_id,
                $language,
                $translation->id
            )->afterCommit();
        });

        /*
        |--------------------------------------------------------------------------
        | TRANSLATION UPDATED
        |--------------------------------------------------------------------------
        */

        static::updated(function ($translation) {

            /*
            |--------------------------------------------------------------------------
            | Only regenerate when actual content changed
            |--------------------------------------------------------------------------
            */

            if (
                ! config('ai.tts_enabled', true)
                || ! $translation->wasChanged('content')
            ) {
                return;
            }

            $topicContent = $translation->topicContent;

            /*
            |--------------------------------------------------------------------------
            | Only TEXT content
            |--------------------------------------------------------------------------
            */

            if (
                ! $topicContent
                || $topicContent->type !== 'text'
            ) {
                return;
            }

            $language = strtolower(
                trim($translation->language_code)
            );

            /*
            |--------------------------------------------------------------------------
            | English handled by TopicContent
            |--------------------------------------------------------------------------
            */

            if ($language === 'en') {
                return;
            }

            Log::channel('ai')->info(
                'TRANSLATION TTS UPDATE - DISPATCHING JOB',
                [
                    'content_id' => $translation->topic_content_id,
                    'translation_id' => $translation->id,
                    'language' => $language,
                ]
            );

            GenerateTopicContentAudioJob::dispatch(
                $translation->topic_content_id,
                $language,
                $translation->id
            )->afterCommit();
        });
    }

    /*
    |--------------------------------------------------------------------------
    | RELATIONSHIP
    |--------------------------------------------------------------------------
    */

    public function topicContent()
    {
        return $this->belongsTo(
            TopicContent::class,
            'topic_content_id'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | AUDIO URL
    |--------------------------------------------------------------------------
    */

    public function getAudioUrlAttribute(): ?string
    {
        if (! $this->audio_path) {
            return null;
        }

        $path = str_starts_with(
            $this->audio_path,
            'public/'
        )
            ? $this->audio_path
            : 'public/' . ltrim(
                $this->audio_path,
                '/'
            );

        return asset($path);
    }
}