<?php

namespace App\Models;

class TopicContent extends BaseModel
{
    protected $hasPublishStatus = true;
    const PUBLISH_DRAFT = 'draft';
    const PUBLISH_PUBLISHED = 'published';
    const PUBLISH_UNPUBLISHED = 'unpublished';


    protected $fillable = [
        'topic_id',
        'type',
        'title',
        'content',
        'meta',
        'order',
        'status',
        'publish_status',
        'created_by'
    ];

    protected $casts = [
        'meta' => 'array',
        'status' => 'boolean',
        'publish_status' => 'string',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function topic()
    {
        return $this->belongsTo(Topic::class)->withTrashed();
    }

    public function progress()
    {
        return $this->hasMany(UserContentProgress::class, 'topic_content_id');
    }

    public function translations()
    {
        return $this->hasMany(TopicContentTranslation::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes (Optional but useful)
    |--------------------------------------------------------------------------
    */

    public function scopeOrdered($query)
    {
        return $query->orderBy('order');
    }

    /*
    |--------------------------------------------------------------------------
    | Cascade Soft Delete
    |--------------------------------------------------------------------------
    */

    public function cascadeSoftDelete()
    {
        // ❗ IMPORTANT:
        // We DO NOT delete user progress
        // Reason: audit + resume + reporting
    }

    /*
    |--------------------------------------------------------------------------
    | Cascade Restore
    |--------------------------------------------------------------------------
    */

    public function cascadeRestore()
    {
        // Nothing required
        // progress already exists
    }

    public function isPublished(): bool
    {
        return $this->publish_status === self::PUBLISH_PUBLISHED;
    }

    public function isDraft(): bool
    {
        return $this->publish_status === self::PUBLISH_DRAFT;
    }

    public function isUnpublished(): bool
    {
        return $this->publish_status === self::PUBLISH_UNPUBLISHED;
    }
}
