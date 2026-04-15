<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Faq extends Model
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

    // 🔹 Polymorphic Relation
    public function faqable()
    {
        return $this->morphTo();
    }

    // 🔹 Translations
    public function translations()
    {
        return $this->hasMany(FaqTranslation::class);
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
    | Scopes (Optional but useful)
    |--------------------------------------------------------------------------
    */

    public function scopeActive($query)
    {
        return $query->where('status', true);
    }
}
