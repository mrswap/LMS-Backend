<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TopicContent extends Model
{
    protected $fillable = [
        'topic_id',
        'type',
        'title',
        'content',
        'meta',
        'order',
        'status',
        'created_by'
    ];

    protected $casts = [
        'meta' => 'array',
        'status' => 'boolean',
    ];

    public function topic()
    {
        return $this->belongsTo(Topic::class);
    }

    public function progress()
    {
        return $this->hasMany(UserContentProgress::class, 'topic_content_id');
    }

    public function translations()
    {
        return $this->hasMany(TopicContentTranslation::class);
    }
}
