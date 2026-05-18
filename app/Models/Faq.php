<?php

namespace App\Models;

use App\Models\Traits\HasPublishStatus;

class Faq extends BaseModel
{
    /*
    |--------------------------------------------------------------------------
    | Fillable
    |--------------------------------------------------------------------------
    */
    protected $fillable = [
        'faqable_id',
        'faqable_type',
        'image',
        'status',
        'created_by'
    ];

    /*
    |--------------------------------------------------------------------------
    | Casts
    |--------------------------------------------------------------------------
    */
    protected $casts = [
        'status' => 'boolean',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function faqable()
    {
        return $this->morphTo()->withTrashed();
    }

    public function translations()
    {
        return $this->hasMany(FaqTranslation::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    /*
    |--------------------------------------------------------------------------
    | Accessors
    |--------------------------------------------------------------------------
    */

    public function getImageAttribute($value)
    {
        if (empty($value)) {
            return url('public/uploads/logo.png');
        }

        // already full URL
        if (filter_var($value, FILTER_VALIDATE_URL)) {
            return $value;
        }

        return url('public/' . ltrim($value, '/'));
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

    /*
    |--------------------------------------------------------------------------
    | Cascade Soft Delete
    |--------------------------------------------------------------------------
    */

    public function cascadeSoftDelete()
    {
        $this->translations()
            ->cursor()
            ->each(function ($translation) {
                $translation->delete();
            });
    }
    /*
    |--------------------------------------------------------------------------
    | Cascade Restore
    |--------------------------------------------------------------------------
    */

    public function cascadeRestore()
    {
        $this->translations()
            ->withTrashed()
            ->cursor()
            ->each(function ($translation) {
                $translation->restore();
            });
    }
}
