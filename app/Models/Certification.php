<?php

namespace App\Models;

use App\Models\Module;
use App\Models\Chapter;


class Certification extends BaseModel
{
    protected $fillable = [
        'user_id',
        'program_id',
        'level_id',
        'topic_id',
        'module_id',
        'chapter_id',
        'type',
        'assessment_attempt_id',
        'certificate_id',
        'score',
        'percentage',
        'issued_at',
        'status',
        'file',
        'meta'
    ];

    protected $casts = [
        'issued_at' => 'datetime:Y-m-d H:i:s',
        'status'     => 'boolean',
        'meta'       => 'array',
        'score'      => 'float',
        'percentage' => 'float',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function user()
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function program()
    {
        return $this->belongsTo(Program::class)->withTrashed();
    }

    public function level()
    {
        return $this->belongsTo(Level::class)->withTrashed();
    }

    public function module()
    {
        return $this->belongsTo(
            Module::class,
            'module_id'
        )->withTrashed();
    }

    public function chapter()
    {
        return $this->belongsTo(
            Chapter::class,
            'chapter_id'
        )->withTrashed();
    }

    public function topic()
    {
        return $this->belongsTo(Topic::class)->withTrashed();
    }

    public function attempt()
    {
        return $this->belongsTo(AssessmentAttempt::class, 'assessment_attempt_id')->withTrashed();
    }

    /*
    |--------------------------------------------------------------------------
    | Accessors
    |--------------------------------------------------------------------------
    */

    public function getFileAttribute($value)
    {
        return $value ? url('public/' . ltrim($value, '/')) : null;
    }

    public function getStatusLabelAttribute()
    {
        return $this->status ? 'Active' : 'Revoked';
    }

    /*
    |--------------------------------------------------------------------------
    | Boot Logic (CRITICAL)
    |--------------------------------------------------------------------------
    */

    protected static function booted()
    {
        parent::booted();

        static::updating(function ($cert) {

            // ❗ Prevent modification after issue
            if ($cert->exists && $cert->issued_at) {

                $allowed = ['status', 'updated_at'];

                foreach ($cert->getDirty() as $field => $value) {
                    if (!in_array($field, $allowed)) {
                        throw new \Exception('Certification record cannot be modified after issuance.');
                    }
                }
            }
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Cascade Soft Delete
    |--------------------------------------------------------------------------
    */

    public function cascadeSoftDelete()
    {
        // ❗ DO NOTHING
        // Certification must not affect other data
    }

    /*
    |--------------------------------------------------------------------------
    | Cascade Restore
    |--------------------------------------------------------------------------
    */

    public function cascadeRestore()
    {
        // nothing required
    }
}
