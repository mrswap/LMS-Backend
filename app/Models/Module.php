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
        return $value ? url($value) : null;
    }
}