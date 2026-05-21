<?php

namespace App\Models;

class TopicAiContext extends BaseModel
{
    protected $fillable = [

        'topic_id',
        'context',
        'context_length',
        'generated_at',
    ];

    protected $casts = [
        'generated_at' => 'datetime',
        'context_length' => 'integer',
    ];

    public function topic()
    {
        return $this->belongsTo(Topic::class);
    }
}
