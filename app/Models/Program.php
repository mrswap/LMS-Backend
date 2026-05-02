<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;

class Program extends BaseModel
{
    protected $fillable = [
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

    public function levels()
    {
        return $this->hasMany(Level::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    public function translations()
    {
        return $this->hasMany(ProgramTranslation::class);
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
    | Helper: Get Translation with Fallback
    |--------------------------------------------------------------------------
    */
    public function getTranslation($lang = null)
    {
        $lang = $lang ?? app()->getLocale();

        $defaultLang = \App\Models\Language::where('is_default', true)->value('code');

        $translation = $this->translations
            ->where('language_code', $lang)
            ->first();

        if (!$translation) {
            $translation = $this->translations
                ->where('language_code', $defaultLang)
                ->first();
        }

        return $translation;
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
        $this->levels()->get()->each->delete();
    }

    /*
    |--------------------------------------------------------------------------
    | Cascade Restore
    |--------------------------------------------------------------------------
    */

    public function cascadeRestore()
    {
        $this->levels()->withTrashed()->get()->each->restore();
    }
}
