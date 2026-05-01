<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CertificateSetting extends Model
{
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

    protected $appends = [
        'company_logo_url',
        'signer_signature_url'
    ];

    /*
    |--------------------------------------------------
    | 🔥 FULL URL GENERATOR (REUSABLE)
    |--------------------------------------------------
    */
    private function getFullUrl($path)
    {
        if (!$path) return null;

        // already full url
        if (str_starts_with($path, 'http')) {
            return $path;
        }

        // remove "public/" prefix if exists
        $path = str_replace('public/', '', $path);

        return url($path);
    }

    /*
    |--------------------------------------------------
    | 🖼 COMPANY LOGO URL
    |--------------------------------------------------
    */
    public function getCompanyLogoUrlAttribute()
    {
        return $this->getFullUrl($this->company_logo);
    }

    /*
    |--------------------------------------------------
    | ✍️ SIGNATURE URL
    |--------------------------------------------------
    */
    public function getSignerSignatureUrlAttribute()
    {
        return $this->getFullUrl($this->signer_signature);
    }
}
