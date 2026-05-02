<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;

class Level extends BaseModel
{
    protected $fillable = [
        'program_id',
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

    public function modules()
    {
        return $this->hasMany(Module::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    public function faqs()
    {
        return $this->morphMany(\App\Models\Faq::class, 'faqable');
    }

    public function translations()
    {
        return $this->hasMany(LevelTranslation::class);
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
    | Accessors
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
        $this->modules()->get()->each->delete();
    }

    /*
    |--------------------------------------------------------------------------
    | Cascade Restore
    |--------------------------------------------------------------------------
    */

    public function cascadeRestore()
    {
        $this->modules()->withTrashed()->get()->each->restore();
    }

    /*
    |--------------------------------------------------------------------------
    | Booted (Hierarchy Sync)
    |--------------------------------------------------------------------------
    */

    protected static function booted()
    {
        static::updated(function ($level) {

            if ($level->wasChanged('program_id')) {

                $programId = $level->program_id;

                // 🔹 Update modules
                $level->modules()->update([
                    'program_id' => $programId,
                ]);

                // 🔹 Update chapters
                \App\Models\Chapter::where('level_id', $level->id)->update([
                    'program_id' => $programId,
                ]);

                // 🔹 Update topics
                \App\Models\Topic::where('level_id', $level->id)->update([
                    'program_id' => $programId,
                ]);
            }
        });
    }
}