<?php

namespace App\Models;

class SmtpSetting extends BaseModel
{
    protected $fillable = [
        'mailer',
        'host',
        'port',
        'username',
        'password',
        'encryption',
        'from_address',
        'from_name',
    ];

    protected $casts = [
        'port' => 'integer',
    ];

    /*
    |--------------------------------------------------------------------------
    | Accessors (Security)
    |--------------------------------------------------------------------------
    */

    public function getPasswordAttribute($value)
    {
        // ❗ Optional: mask in API response
        return $value ? '********' : null;
    }

    /*
    |--------------------------------------------------------------------------
    | Boot Logic (CRITICAL)
    |--------------------------------------------------------------------------
    */

    protected static function booted()
    {
        parent::booted();

        static::creating(function ($model) {

            // ❗ Ensure only one config exists
            if (self::count() > 0) {
                throw new \Exception('SMTP configuration already exists. Update instead.');
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
    }

    public function cascadeRestore()
    {
        // nothing required
    }
}
