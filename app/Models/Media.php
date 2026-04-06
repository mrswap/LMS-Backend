<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Media extends Model
{
    /*
    |--------------------------------------------------------------------------
    | CONSTANTS
    |--------------------------------------------------------------------------
    */
    const UPLOAD_PATH = 'uploads/content-management/media';

    /*
    |--------------------------------------------------------------------------
    | FILLABLE
    |--------------------------------------------------------------------------
    */
    protected $fillable = [
        'title',
        'description',
        'type',
        'file',
        'external_url',
        'shortcode',
        'disk',
        'status',
        'created_by',
    ];

    /*
    |--------------------------------------------------------------------------
    | APPENDS (auto added in API response)
    |--------------------------------------------------------------------------
    */
    protected $appends = [
        'full_url',
        'creator_name',
    ];

    /*
    |--------------------------------------------------------------------------
    | RELATIONS
    |--------------------------------------------------------------------------
    */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /*
    |--------------------------------------------------------------------------
    | ACCESSORS
    |--------------------------------------------------------------------------
    */

    // 🔹 Full URL (local + S3 compatible)
    public function getFullUrlAttribute()
    {
        // External (YouTube/Vimeo)
        if ($this->external_url) {
            return $this->external_url;
        }

        if (!$this->file) {
            return null;
        }

        return url(Storage::disk($this->disk)->url($this->file));
    }

    // 🔹 Creator Name
    public function getCreatorNameAttribute()
    {
        return $this->creator?->name;
    }

    /*
    |--------------------------------------------------------------------------
    | MUTATORS (optional future use)
    |--------------------------------------------------------------------------
    */

    // Example: auto-trim title
    public function setTitleAttribute($value)
    {
        $this->attributes['title'] = trim($value);
    }

    /*
    |--------------------------------------------------------------------------
    | SCOPES (useful for filters)
    |--------------------------------------------------------------------------
    */

    public function scopeActive($query)
    {
        return $query->where('status', 1);
    }

    public function scopeType($query, $type)
    {
        return $query->where('type', $type);
    }

    /*
    |--------------------------------------------------------------------------
    | HELPERS (future usage)
    |--------------------------------------------------------------------------
    */

    // Check if external media
    public function isExternal()
    {
        return !is_null($this->external_url);
    }
}
