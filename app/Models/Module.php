<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;

class Module extends BaseModel
{
    protected $hasPublishStatus = true;
    const PUBLISH_DRAFT = 'draft';
    const PUBLISH_PUBLISHED = 'published';
    const PUBLISH_UNPUBLISHED = 'unpublished';

    protected $fillable = [
        'program_id',
        'level_id',
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

    public function chapters()
    {
        return $this->hasMany(Chapter::class);
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
        return $this->hasMany(ModuleTranslation::class);
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
        $this->chapters()->get()->each->delete();
    }

    /*
    |--------------------------------------------------------------------------
    | Cascade Restore
    |--------------------------------------------------------------------------
    */

    public function cascadeRestore()
    {
        $this->chapters()->withTrashed()->get()->each->restore();
    }

    /*
    |--------------------------------------------------------------------------
    | Booted (Hierarchy Sync)
    |--------------------------------------------------------------------------
    */

    protected static function booted()
    {
        parent::booted();

        static::updated(function ($module) {

            if ($module->wasChanged('level_id')) {

                $level = \App\Models\Level::withTrashed()->find($module->level_id);

                if (!$level) return;

                // 🔹 Update chapters
                $module->chapters()->update([
                    'level_id'   => $level->id,
                    'program_id' => $level->program_id,
                ]);

                // 🔹 Update topics
                \App\Models\Topic::where('module_id', $module->id)->update([
                    'level_id'   => $level->id,
                    'program_id' => $level->program_id,
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
