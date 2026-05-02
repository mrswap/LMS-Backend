<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CertificateSetting extends Model
{
    /*
    |--------------------------------------------------
    | 📌 MASS ASSIGNABLE
    |--------------------------------------------------
    */
    protected $fillable = [
        'company_name',
        'company_logo',
        'tagline',
        'certificate_heading',
        'signer_name',
        'signer_designation',
        'signer_signature',
        'content',
        'footer_text',
    ];

    /*
    |--------------------------------------------------
    | 📌 AUTO APPEND (FRONTEND READY)
    |--------------------------------------------------
    */
    protected $appends = [
        'company_logo_url',
        'signer_signature_url'
    ];

    /*
    |--------------------------------------------------
    | 📌 FILE FIELDS (CENTRAL CONTROL 🔥)
    |--------------------------------------------------
    */
    protected $fileFields = [
        'company_logo',
        'signer_signature'
    ];

    /*
    |--------------------------------------------------
    | 🔥 FULL URL GENERATOR (SMART HANDLER)
    |--------------------------------------------------
    */
    protected function getFullUrl($path)
    {
        if (!$path) return null;

        // already full URL
        if (str_starts_with($path, 'http')) {
            return $path;
        }

        // normalize
        $path = ltrim($path, '/');

        // ensure public/ prefix
        if (!str_starts_with($path, 'public/')) {
            $path = 'public/' . $path;
        }

        return url($path);
    }

    /*
    |--------------------------------------------------
    | 🔄 GENERIC FILE ACCESSOR (ADVANCED 🔥)
    |--------------------------------------------------
    */
    public function __get($key)
    {
        // handle *_url dynamically
        if (str_ends_with($key, '_url')) {

            $field = str_replace('_url', '', $key);

            if (in_array($field, $this->fileFields)) {
                return $this->getFullUrl($this->attributes[$field] ?? null);
            }
        }

        return parent::__get($key);
    }

    /*
    |--------------------------------------------------
    | 🖼 EXPLICIT ACCESSORS (SAFE FALLBACK)
    |--------------------------------------------------
    */

    public function getCompanyLogoUrlAttribute()
    {
        return $this->getFullUrl($this->company_logo);
    }

    public function getSignerSignatureUrlAttribute()
    {
        return $this->getFullUrl($this->signer_signature);
    }
}