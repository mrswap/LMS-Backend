<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LevelTranslation extends Model
{
    protected $fillable = [
        'level_id',
        'language_code',
        'title',
        'description',
    ];

    public function level()
    {
        return $this->belongsTo(Level::class);
    }
}