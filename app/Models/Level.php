<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class Level extends Model
{
    protected $fillable = [
        'program_id',
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

    public function program()
    {
        return $this->belongsTo(Program::class);
    }

    public function modules()
    {
        return $this->hasMany(Module::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
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
    | Accessors
    |--------------------------------------------------------------------------
    */

    public function getThumbnailAttribute($value)
    {
        return $value ? url($value) : null;
    }
}