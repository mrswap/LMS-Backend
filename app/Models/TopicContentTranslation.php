<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TopicContentTranslation extends Model
{
    protected $fillable = [
        'topic_content_id',
        'language_code',
        'title',
        'content',
    ];

    public function content()
    {
        return $this->belongsTo(TopicContent::class, 'topic_content_id');
    }
}