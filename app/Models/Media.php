<?php

namespace App\Models;

use Illuminate\Support\Facades\Storage;

class Media extends BaseModel
{
    const UPLOAD_PATH = 'uploads/content-management/media';

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

    protected $casts = [
        'status' => 'boolean',
    ];

    protected $appends = [
        'full_url',
        'creator_name',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    /*
    |--------------------------------------------------------------------------
    | Accessors
    |--------------------------------------------------------------------------
    */

    public function getFullUrlAttribute()
    {
        // External (YouTube/Vimeo etc.)
        if ($this->external_url) {
            return $this->external_url;
        }

        if (!$this->file) {
            return null;
        }

        $disk = $this->disk ?? config('filesystems.default');

        return url(Storage::disk($disk)->url($this->file));
    }

    public function getCreatorNameAttribute()
    {
        return $this->creator?->name;
    }

    /*
    |--------------------------------------------------------------------------
    | Mutators
    |--------------------------------------------------------------------------
    */

    public function setTitleAttribute($value)
    {
        $this->attributes['title'] = trim($value);
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

    public function scopeActive($query)
    {
        return $query->where('status', true);
    }

    public function scopeType($query, $type)
    {
        return $query->where('type', $type);
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    public function isExternal()
    {
        return !is_null($this->external_url);
    }

    /*
    |--------------------------------------------------------------------------
    | Cascade Soft Delete
    |--------------------------------------------------------------------------
    */

    public function cascadeSoftDelete()
    {
        // ❗ DO NOT delete physical file
        // Only DB soft delete
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
