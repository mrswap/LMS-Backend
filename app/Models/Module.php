<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class Module extends Model
{
    protected $fillable = [
        'program_id',
        'level_id',
        'title',
        'description',
        'thumbnail',
        'status',
        'created_by',
    ];

    protected $casts = [
        'status' => 'boolean',
    ];

    public function program()
    {
        return $this->belongsTo(Program::class);
    }

    public function level()
    {
        return $this->belongsTo(Level::class);
    }

    public function chapters()
    {
        return $this->hasMany(Chapter::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // 🔥 REQUIRED
    public function translations()
    {
        return $this->hasMany(ModuleTranslation::class);
    }

    public function scopeActive(Builder $query)
    {
        return $query->where('status', true);
    }

    public function getThumbnailAttribute($value)
    {
        return $value ? url('public/' . ltrim($value, '/')) : null;
    }

    protected static function boot()
    {
        parent::boot();

        static::updated(function ($module) {

            if ($module->wasChanged('level_id')) {

                $level = \App\Models\Level::with('program')->find($module->level_id);

                if (!$level) return;

                // 🔹 Update all chapters
                $module->chapters()->update([
                    'level_id'   => $level->id,
                    'program_id' => $level->program_id,
                ]);

                // 🔹 Update all topics under those chapters
                \App\Models\Topic::where('module_id', $module->id)->update([
                    'level_id'   => $level->id,
                    'program_id' => $level->program_id,
                ]);
            }
        });
    }
}
