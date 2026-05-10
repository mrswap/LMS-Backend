<?php

namespace App\Models;

use App\Models\Traits\HasPublishStatus;

class Faq extends BaseModel
{
    use HasPublishStatus;
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
        return $value
            ? url('public/' . ltrim($value, '/'))
            : null;
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
