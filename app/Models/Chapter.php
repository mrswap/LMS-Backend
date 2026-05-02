<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;

class Chapter extends BaseModel
{
    protected $fillable = [
        'program_id',
        'level_id',
        'module_id',
        'title',
        'description',
        'thumbnail',
        'status',
        'created_by',
    ];

    protected $casts = [
        'status' => 'boolean',
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
        $this->topics()->get()->each->delete();
    }

    /*
    |--------------------------------------------------------------------------
    | Cascade Restore
    |--------------------------------------------------------------------------
    */

    public function cascadeRestore()
    {
        $this->topics()->withTrashed()->get()->each->restore();
    }

    /*
    |--------------------------------------------------------------------------
    | Booted (Hierarchy Sync)
    |--------------------------------------------------------------------------
    */

    protected static function booted()
    {
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
}