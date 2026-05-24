<?php

namespace App\Models;

use Illuminate\Support\Facades\Log;
use App\Models\Traits\HasPublishStatus;
use App\Services\AI\TopicContextService;
use App\Jobs\GenerateTopicContentAudioJob;

class TopicContent extends BaseModel
{
    use HasPublishStatus;

    /*
    |--------------------------------------------------------------------------
    | GOVERNANCE
    |--------------------------------------------------------------------------
    */

    protected $hasPublishStatus = true;

    const PUBLISH_DRAFT = 'draft';
    const PUBLISH_PUBLISHED = 'published';
    const PUBLISH_UNPUBLISHED = 'unpublished';

    /*
    |--------------------------------------------------------------------------
    | RUNTIME FLAGS
    |--------------------------------------------------------------------------
    |
    | IMPORTANT:
    | Runtime only property
    | DB me save nahi hogi
    |
    */

    protected bool $shouldGenerateAudio = false;

    /*
    |--------------------------------------------------------------------------
    | FILLABLE
    |--------------------------------------------------------------------------
    */

    protected $fillable = [
        'topic_id',
        'type',
        'title',
        'content',
        'meta',
        'order',
        'status',
        'publish_status',
        'created_by',

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
        'meta' => 'array',
        'status' => 'boolean',
        'publish_status' => 'string',
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
    | MODEL EVENTS
    |--------------------------------------------------------------------------
    */

    protected static function booted()
    {

        parent::booted();

        /*
    |--------------------------------------------------------------------------
    | CREATE
    |--------------------------------------------------------------------------
    */

        static::creating(function ($content) {

            $content->shouldGenerateAudio =
                $content->type === 'text';
        });

        /*
    |--------------------------------------------------------------------------
    | UPDATE
    |--------------------------------------------------------------------------
    */

        static::updating(function ($content) {

            $content->shouldGenerateAudio =
                $content->type === 'text'
                && (
                    $content->isDirty('content')
                    || $content->isDirty('title')
                );

            Log::info('UPDATE AUDIO CHECK', [
                'isDirtyContent' => $content->isDirty('content'),
                'isDirtyTitle' => $content->isDirty('title'),
                'shouldGenerateAudio' => $content->shouldGenerateAudio,
            ]);
        });

        /*
    |--------------------------------------------------------------------------
    | SAVED
    |--------------------------------------------------------------------------
    */

        static::saved(function ($content) {

            try {

                if ($content->topic) {

                    app(TopicContextService::class)
                        ->cache($content->topic);

                    Log::channel('ai')->info(
                        'Topic AI Context Regenerated',
                        [
                            'topic_id' => $content->topic_id,
                            'content_id' => $content->id,
                        ]
                    );
                }

                if (
                    env('OPENAI_TTS_ENABLED', true)
                    && $content->shouldGenerateAudio
                ) {

                    Log::info('DISPATCHING AUDIO JOB', [
                        'content_id' => $content->id
                    ]);

                    GenerateTopicContentAudioJob::dispatch(
                        $content->id
                    );

                    Log::channel('ai')->info(
                        'Topic Content TTS Job Dispatched',
                        [
                            'topic_id' => $content->topic_id,
                            'content_id' => $content->id,
                        ]
                    );
                }
            } catch (\Throwable $e) {

                Log::error(
                    'AI Context / TTS Sync Failed',
                    [
                        'message' => $e->getMessage(),
                    ]
                );
            }
        });
    }
    /*
    |--------------------------------------------------------------------------
    | ACCESSORS
    |--------------------------------------------------------------------------
    */

    public function getAudioUrlAttribute(): ?string
    {
        if (!$this->audio_path) {
            return null;
        }

        $path = str_starts_with($this->audio_path, 'public/')
            ? $this->audio_path
            : 'public/' . ltrim($this->audio_path, '/');

        return asset($path);
    }
    /*
    |--------------------------------------------------------------------------
    | RELATIONSHIPS
    |--------------------------------------------------------------------------
    */

    public function topic()
    {
        return $this->belongsTo(Topic::class)
            ->withTrashed();
    }

    public function progress()
    {
        return $this->hasMany(
            UserContentProgress::class,
            'topic_content_id'
        );
    }

    public function translations()
    {
        return $this->hasMany(
            TopicContentTranslation::class
        );
    }

    public function creator()
    {
        return $this->belongsTo(
            User::class,
            'created_by'
        )->withTrashed();
    }

    /*
    |--------------------------------------------------------------------------
    | SCOPES
    |--------------------------------------------------------------------------
    */

    public function scopeOrdered($query)
    {
        return $query->orderBy('order');
    }

    /*
    |--------------------------------------------------------------------------
    | CASCADE SOFT DELETE
    |--------------------------------------------------------------------------
    */

    public function cascadeSoftDelete()
    {
        /*
        |--------------------------------------------------------------------------
        | IMPORTANT
        |--------------------------------------------------------------------------
        |
        | We DO NOT delete user progress
        | Reason:
        | audit + reporting + resume support
        |
        */
    }

    /*
    |--------------------------------------------------------------------------
    | CASCADE RESTORE
    |--------------------------------------------------------------------------
    */

    public function cascadeRestore()
    {
        // Nothing required
    }

    /*
    |--------------------------------------------------------------------------
    | PUBLISH HELPERS
    |--------------------------------------------------------------------------
    */

    public function isPublished(): bool
    {
        return $this->publish_status
            === self::PUBLISH_PUBLISHED;
    }

    public function isDraft(): bool
    {
        return $this->publish_status
            === self::PUBLISH_DRAFT;
    }

    public function isUnpublished(): bool
    {
        return $this->publish_status
            === self::PUBLISH_UNPUBLISHED;
    }
}
