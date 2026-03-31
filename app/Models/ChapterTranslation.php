<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChapterTranslation extends Model
{
    protected $fillable = [
        'chapter_id',
        'language_code',
        'title',
        'description',
    ];

    public function chapter()
    {
        return $this->belongsTo(Chapter::class);
    }
}