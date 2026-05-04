<?php

namespace App\Models;

class Setting extends BaseModel
{
    protected $fillable = ['key', 'value'];

    /*
    |--------------------------------------------------------------------------
    | Boot (Cache Clear)
    |--------------------------------------------------------------------------
    */
    protected static function booted()
    {
        static::saved(fn($setting) => cache()->forget("setting_{$setting->key}"));
        static::deleted(fn($setting) => cache()->forget("setting_{$setting->key}"));
        static::restored(fn($setting) => cache()->forget("setting_{$setting->key}"));
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
    | 🔐 ENCRYPTION HELPERS
    |--------------------------------------------------------------------------
    */
    public static function setEncrypted($key, $value)
    {
        return self::setValue($key, encrypt($value));
    }

    public static function getDecrypted($key, $default = null)
    {
        try {
            $value = self::getValue($key);
            return $value ? decrypt($value) : $default;
        } catch (\Throwable $e) {
            return $default;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | 🔥 FIREBASE FULL JSON SUPPORT
    |--------------------------------------------------------------------------
    */
    public static function setFirebaseJson($json)
    {
        $decoded = json_decode($json, true);

        if (!$decoded) {
            throw new \Exception('Invalid Firebase JSON');
        }

        return self::setEncrypted('firebase_service_account', $json);
    }

    public static function getFirebaseJson()
    {
        return self::getDecrypted('firebase_service_account');
    }

    public static function getFirebaseConfig()
    {
        $json = self::getFirebaseJson();
        return $json ? json_decode($json, true) : null;
    }

    /*
    |--------------------------------------------------------------------------
    | GET FULL URL
    |--------------------------------------------------------------------------
    */
    public static function getFullUrl($key)
    {
        $value = self::getValue($key);

        if (!$value) return null;

        return str_starts_with($value, 'http')
            ? $value
            : url(ltrim($value, '/'));
    }

    /*
    |--------------------------------------------------------------------------
    | GET ALL SETTINGS (PUBLIC)
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
    | NO CASCADE
    |--------------------------------------------------------------------------
    */
    public function cascadeSoftDelete() {}
    public function cascadeRestore() {}
}
