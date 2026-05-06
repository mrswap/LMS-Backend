<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;


class Language extends BaseModel
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
        'is_active'  => 'boolean',
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
    | Accessors
    |--------------------------------------------------------------------------
    */

    public function getTranslationFileAttribute($value)
    {
        return $value ? url($value) : null;
    }

    public function getThumbnailAttribute($value)
    {
        return $value ? url('public/' . ltrim($value, '/')) : null;
    }

    /*
    |--------------------------------------------------------------------------
    | Boot Logic (CRITICAL)
    |--------------------------------------------------------------------------
    */

    protected static function booted()
    {
        parent::booted();

        static::deleting(function ($language) {

            // ❗ Block default language deletion
            if ($language->is_default) {
                throw new \Exception('Default language cannot be deleted.');
            }

            // ❗ Optional (recommended): block if translations exist
            $used = DB::table('program_translations')->where('language_code', $language->code)->exists()
                || DB::table('level_translations')->where('language_code', $language->code)->exists()
                || DB::table('module_translations')->where('language_code', $language->code)->exists()
                || DB::table('chapter_translations')->where('language_code', $language->code)->exists()
                || DB::table('topic_translations')->where('language_code', $language->code)->exists()
                || DB::table('topic_content_translations')->where('language_code', $language->code)->exists()
                || DB::table('faq_translations')->where('language_code', $language->code)->exists();

            if ($used) {
                throw new \Exception('Language is in use and cannot be deleted.');
            }
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Cascade Soft Delete
    |--------------------------------------------------------------------------
    */

    public function cascadeSoftDelete()
    {
        // ❗ DO NOTHING
        // translations should NOT auto delete
    }

    /*
    |--------------------------------------------------------------------------
    | Cascade Restore
    |--------------------------------------------------------------------------
    */

    public function cascadeRestore()
    {
        // nothing required
    }
}
