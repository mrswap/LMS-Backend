<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\CertificateSetting;

class CertificateSettingSeeder extends Seeder
{
    public function run(): void
    {
        CertificateSetting::truncate();

        CertificateSetting::create([
            'company_name' => 'Avante Medical',
            'company_logo' => null,
            'tagline' => 'Avante Sales Training App',
            'certificate_heading' => 'Certificate of Achievement',
            'signer_name' => 'Dr. John Doe',
            'signer_designation' => 'Head of Training',
            'signer_signature' => null,
            'footer_text' => 'This certificate is digitally generated and does not require a physical signature.',

            'content' => ''
        ]);
    }
}
