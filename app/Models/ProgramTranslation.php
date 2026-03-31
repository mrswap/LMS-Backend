<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProgramTranslation extends Model
{
    protected $fillable = [
        'program_id',
        'language_code',
        'title',
        'description',
    ];

    public function program()
    {
        return $this->belongsTo(Program::class);
    }
}