<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;

class Topic extends BaseModel
{
    protected $hasPublishStatus = true;
    const PUBLISH_DRAFT = 'draft';
    const PUBLISH_PUBLISHED = 'published';
    const PUBLISH_UNPUBLISHED = 'unpublished';

    protected $fillable = [
        'program_id',
        'level_id',
        'module_id',
        'chapter_id',
        'title',
        'description',
        'thumbnail',
        'estimated_duration',
        'status',
        'created_by',
        'publish_status',
    ];

    protected $casts = [
        'status' => 'boolean',
        'estimated_duration' => 'integer',
        'publish_status' => 'string',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function program()
    {
        return $this->belongsTo(Program::class)->withTrashed();
    }

    public function level()
    {
        return $this->belongsTo(Level::class)->withTrashed();
    }

    public function module()
    {
        return $this->belongsTo(Module::class)->withTrashed();
    }

    public function chapter()
    {
        return $this->belongsTo(Chapter::class)->withTrashed();
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    public function faqs()
    {
        return $this->morphMany(\App\Models\Faq::class, 'faqable');
    }

    public function progress()
    {
        // explicit FK keeps it predictable
        return $this->hasMany(UserProgress::class, 'topic_id');
    }

    public function translations()
    {
        return $this->hasMany(TopicTranslation::class);
    }

    public function contents()
    {
        return $this->hasMany(TopicContent::class)->orderBy('order');
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

    public function scopeActive(Builder $query)
    {
        return $query->where('status', true);
    }

    public function scopePublished(Builder $query)
    {
        return $query->where(
            'publish_status',
            self::PUBLISH_PUBLISHED
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Accessor
    |--------------------------------------------------------------------------
    */

    public function getThumbnailAttribute($value)
    {
        return $value ? url('public/' . ltrim($value, '/')) : null;
    }

    /*
    |--------------------------------------------------------------------------
    | Cascade Soft Delete
    |--------------------------------------------------------------------------
    */

    public function cascadeSoftDelete()
    {
        // delete all contents under this topic
        $this->contents()->get()->each->delete();
    }

    /*
    |--------------------------------------------------------------------------
    | Cascade Restore
    |--------------------------------------------------------------------------
    */

    public function cascadeRestore()
    {
        // restore all contents (including previously soft-deleted)
        $this->contents()->withTrashed()->get()->each->restore();
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
