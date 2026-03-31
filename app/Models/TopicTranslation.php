<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TopicTranslation extends Model
{
    protected $fillable = [
        'topic_id',
        'language_code',
        'title',
        'description',
    ];

    public function topic()
    {
        return $this->belongsTo(Topic::class);
    }
}