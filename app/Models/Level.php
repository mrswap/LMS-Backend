<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class Level extends Model
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
        return $this->belongsTo(Program::class);
    }

    public function modules()
    {
        return $this->hasMany(Module::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function faqs()
    {
        return $this->morphMany(\App\Models\Faq::class, 'faqable');
    }

    // 🔥 REQUIRED (for multilingual)
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

    protected static function boot()
    {
        parent::boot();

        static::updated(function ($level) {

            if ($level->wasChanged('program_id')) {

                $programId = $level->program_id;

                // 🔹 Step 1: Update all modules
                $level->modules()->update([
                    'program_id' => $programId,
                ]);

                // 🔹 Step 2: Update all chapters under those modules
                \App\Models\Chapter::where('level_id', $level->id)->update([
                    'program_id' => $programId,
                ]);

                // 🔹 Step 3: Update all topics under those chapters
                \App\Models\Topic::where('level_id', $level->id)->update([
                    'program_id' => $programId,
                ]);
            }
        });
    }
}
