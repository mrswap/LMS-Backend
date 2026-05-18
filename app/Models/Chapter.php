<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use App\Models\Traits\HasPublishStatus;

class Chapter extends BaseModel
{
    use HasPublishStatus;
    protected $hasPublishStatus = true;
    const PUBLISH_DRAFT = 'draft';
    const PUBLISH_PUBLISHED = 'published';
    const PUBLISH_UNPUBLISHED = 'unpublished';

    protected $fillable = [
        'program_id',
        'level_id',
        'module_id',
        'title',
        'description',
        'thumbnail',
        'status',
        'created_by',
        'publish_status',
    ];

    protected $casts = [
        'status' => 'boolean',
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

    public function topics()
    {
        return $this->hasMany(Topic::class);
    }

    public function faqs()
    {
        return $this->morphMany(\App\Models\Faq::class, 'faqable');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    public function translations()
    {
        return $this->hasMany(ChapterTranslation::class);
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
        if (empty($value)) {
            return url('public/uploads/logo.png');
        }

        // already full URL
        if (filter_var($value, FILTER_VALIDATE_URL)) {
            return $value;
        }

        return url('public/' . ltrim($value, '/'));
    }
    
    /*
    |--------------------------------------------------------------------------
    | Cascade Soft Delete
    |--------------------------------------------------------------------------
    */

    public function cascadeSoftDelete()
    {
        $this->topics()->cursor()->each(function ($topic) {
            $topic->delete();
        });

        // faqs
        $this->faqs()
            ->cursor()
            ->each(function ($faq) {
                $faq->delete();
            });
    }

    /*
    |--------------------------------------------------------------------------
    | Cascade Restore
    |--------------------------------------------------------------------------
    */

    public function cascadeRestore()
    {
        $this->topics()
            ->withTrashed()
            ->cursor()
            ->each(function ($topic) {
                $topic->restore();
            });

        $this->faqs()
            ->withTrashed()
            ->cursor()
            ->each(function ($faq) {
                $faq->restore();
            });
    }

    /*
    |--------------------------------------------------------------------------
    | Boot (Hierarchy Sync)
    |--------------------------------------------------------------------------
    */

    protected static function boot()
    {
        parent::boot();

        static::updated(function ($chapter) {

            if ($chapter->wasChanged('module_id')) {

                $module = \App\Models\Module::withTrashed()->find($chapter->module_id);

                if (!$module) return;

                // 🔹 Update topics
                $chapter->topics()->update([
                    'module_id'  => $module->id,
                    'level_id'   => $module->level_id,
                    'program_id' => $module->program_id,
                ]);
            }
        });
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
