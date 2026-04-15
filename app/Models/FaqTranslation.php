<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FaqTranslation extends Model
{
    protected $fillable = [
        'faq_id',
        'language_code',
        'question',
        'answer'
    ];

    public function faq()
    {
        return $this->belongsTo(Faq::class);
    }
}
