<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserContentProgress extends Model
{
    protected $fillable = [
        'user_id',
        'topic_content_id',
        'is_read',
        'read_at'
    ];

    public function content()
    {
        return $this->belongsTo(TopicContent::class, 'topic_content_id');
    }
}
