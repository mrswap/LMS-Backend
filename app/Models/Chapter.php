<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class Chapter extends Model
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

    public function program()
    {
        return $this->belongsTo(Program::class);
    }

    public function level()
    {
        return $this->belongsTo(Level::class);
    }

    public function module()
    {
        return $this->belongsTo(Module::class);
    }

    public function topics()
    {
        return $this->hasMany(Topic::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // 🔥 REQUIRED
    public function translations()
    {
        return $this->hasMany(ChapterTranslation::class);
    }

    public function scopeActive(Builder $query)
    {
        return $query->where('status', true);
    }

    public function getThumbnailAttribute($value)
    {
        return $value ? url($value) : null;
    }
}