<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TopicContentTranslation extends Model
{
    /*
    |--------------------------------------------------------------------------
    | FILLABLE
    |--------------------------------------------------------------------------
    */

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

    /*
    |--------------------------------------------------------------------------
    | CASTS
    |--------------------------------------------------------------------------
    */

    protected $casts = [
        'audio_generated_at' => 'datetime',
    ];

    /*
    |--------------------------------------------------------------------------
    | APPENDS
    |--------------------------------------------------------------------------
    */

    protected $appends = [
        'audio_url',
    ];

    /*
    |--------------------------------------------------------------------------
    | RELATIONSHIPS
    |--------------------------------------------------------------------------
    */

    public function content()
    {
        return $this->belongsTo(
            TopicContent::class,
            'topic_content_id'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | ACCESSORS
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

