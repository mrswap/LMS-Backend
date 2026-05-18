<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ImportLog extends Model
{
    use HasFactory;

    /*
    |--------------------------------------------------------------------------
    | TABLE
    |--------------------------------------------------------------------------
    */

    protected $table = 'import_logs';

    /*
    |--------------------------------------------------------------------------
    | FILLABLE
    |--------------------------------------------------------------------------
    */

    protected $fillable = [

        'program_id',

        'level_id',

        'status',

        'raw_html',

        'meta',

        'error',

        'created_by',
    ];

    /*
    |--------------------------------------------------------------------------
    | CASTS
    |--------------------------------------------------------------------------
    */

    protected $casts = [

        'meta' => 'array',
    ];

    /*
    |--------------------------------------------------------------------------
    | RELATIONSHIPS
    |--------------------------------------------------------------------------
    */

    public function program()
    {
        return $this->belongsTo(
            Program::class,
            'program_id'
        );
    }

    public function level()
    {
        return $this->belongsTo(
            Level::class,
            'level_id'
        );
    }

    public function creator()
    {
        return $this->belongsTo(
            User::class,
            'created_by'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | HELPERS
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

    public function markFailed(
        string $error
    ): void {

        $this->update([

            'status' => 'failed',

            'error' => $error,
        ]);
    }
}