<?php

namespace App\Models;

class TopicContent extends BaseModel
{
    protected $fillable = [
        'topic_id',
        'type',
        'title',
        'content',
        'meta',
        'order',
        'status',
        'created_by'
    ];

    protected $casts = [
        'meta' => 'array',
        'status' => 'boolean',
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
}
