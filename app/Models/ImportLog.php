<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ImportLog extends Model
{
    use HasFactory;

    protected $table = 'import_logs';

    protected $fillable = [

        'program_id',
        'level_id',
        'status',
        'raw_html',
        'meta',
        'error',
        'created_by',
    ];

    protected $casts = [

        'meta' => 'array',
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

    public function level()
    {
        return $this->belongsTo(Level::class);
    }

    public function creator()
    {
        return $this->belongsTo(
            User::class,
            'created_by'
        )->withTrashed();
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    public function markProcessing(): void
    {
        $this->update([
            'status' => 'processing'
        ]);
    }

    public function markCompleted(): void
    {
        $this->update([
            'status' => 'completed',
            'error' => null,
        ]);
    }

    public function markFailed(string $error): void
    {
        $this->update([
            'status' => 'failed',
            'error' => $error,
        ]);
    }
}