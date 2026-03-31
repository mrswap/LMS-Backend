<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class Language extends Model
{
    protected $fillable = [
        'name',
        'code',
        'is_default',
        'is_active',
        'translation_file',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'is_active' => 'boolean',
    ];

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

    public function scopeActive(Builder $query)
    {
        return $query->where('is_active', true);
    }

    public function scopeDefault(Builder $query)
    {
        return $query->where('is_default', true);
    }

    /*
    |--------------------------------------------------------------------------
    | Accessor
    |--------------------------------------------------------------------------
    */

    public function getTranslationFileAttribute($value)
    {
        return $value ? url($value) : null;
    }
}