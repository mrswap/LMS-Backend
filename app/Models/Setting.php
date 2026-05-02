<?php

namespace App\Models;

class Setting extends BaseModel
{
    protected $fillable = ['key', 'value'];

    /*
    |--------------------------------------------------------------------------
    | Boot Logic (Cache Safety)
    |--------------------------------------------------------------------------
    */

    protected static function booted()
    {
        static::saved(function ($setting) {
            cache()->forget("setting_{$setting->key}");
        });

        static::deleted(function ($setting) {
            cache()->forget("setting_{$setting->key}");
        });

        static::restored(function ($setting) {
            cache()->forget("setting_{$setting->key}");
        });
    }

    /*
    |--------------------------------------------------------------------------
    | GET VALUE (CACHED)
    |--------------------------------------------------------------------------
    */

    public static function getValue($key, $default = null)
    {
        return cache()->remember("setting_$key", 3600, function () use ($key, $default) {
            return self::where('key', $key)->value('value') ?? $default;
        });
    }

    /*
    |--------------------------------------------------------------------------
    | SET VALUE
    |--------------------------------------------------------------------------
    */

    public static function setValue($key, $value)
    {
        cache()->forget("setting_$key");

        return self::updateOrCreate(
            ['key' => $key],
            ['value' => $value]
        );
    }

    /*
    |--------------------------------------------------------------------------
    | GET FULL URL
    |--------------------------------------------------------------------------
    */

    public static function getFullUrl($key)
    {
        $value = self::getValue($key);

        if (!$value) {
            return null;
        }

        if (str_starts_with($value, 'http')) {
            return $value;
        }

        return url(ltrim($value, '/'));
    }

    /*
    |--------------------------------------------------------------------------
    | GET ALL SETTINGS
    |--------------------------------------------------------------------------
    */

    public static function getAllFormatted()
    {
        $keys = [
            'company_logo',
            'company_bio',
            'app_ios_store',
            'app_ios_download',
            'app_android_store',
            'app_android_download',
            'contact_heading',
            'contact_text',
            'contact_phone',
            'contact_email',
            'social_facebook',
            'social_linkedin',
            'social_instagram',
            'social_twitter',
            'footer_text',
            'about_us',
            'privacy_policy',
            'terms_conditions'
        ];

        $data = [];

        foreach ($keys as $key) {

            $value = self::getValue($key);

            if ($key === 'company_logo') {
                $value = self::getFullUrl($key);
            }

            $data[$key] = $value;
        }

        return $data;
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
