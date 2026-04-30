<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class Topic extends Model
{
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
    ];

    protected $casts = [
        'status' => 'boolean',
        'estimated_duration' => 'integer',
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

    public function level()
    {
        return $this->belongsTo(Level::class);
    }

    public function module()
    {
        return $this->belongsTo(Module::class);
    }

    public function chapter()
    {
        return $this->belongsTo(Chapter::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function faqs()
    {
        return $this->morphMany(\App\Models\Faq::class, 'faqable');
    }

    public function progress()
    {
        return $this->hasMany(UserProgress::class);
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

    public function translations()
    {
        return $this->hasMany(TopicTranslation::class);
    }

    public function contents()
    {
        return $this->hasMany(TopicContent::class)->orderBy('order');
    }
    
}
