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
            'company_name' => 'Aavnta Medical',
            'company_logo' => null,
            'tagline' => 'Avante Sales Training App',
            'certificate_heading' => 'Certificate of Achievement',
            'signer_name' => 'Dr. John Doe',
            'signer_designation' => 'Head of Training',
            'signer_signature' => null,
            'footer_text' => 'This certificate is digitally generated and does not require a physical signature.',

            'content' => <<<HTML
                            <div style="width:100%; padding:40px; font-family:Arial, sans-serif; background:#f9f9f9; border:10px solid #0d6efd;">
                                <div style="text-align:center;">
                                    <h1 style="margin:0; color:#0d6efd;">Aavnta Medical</h1>
                                    <p style="margin:5px 0; font-size:14px;">Avante Sales Training App</p>
                                </div>

                                <div style="text-align:center; margin-top:30px;">
                                    <h2 style="margin:0; font-size:28px;">{{certificate_heading}}</h2>
                                </div>

                                <div style="margin-top:40px; text-align:center; font-size:18px;">
                                    This is to certify that
                                </div>

                                <div style="text-align:center; margin:20px 0;">
                                    <h2>{{name}}</h2>
                                    <p>Employee ID: {{employee_id}}</p>
                                    <p>Email: {{email}}</p>
                                </div>

                                <div style="text-align:center;">
                                    has successfully completed the
                                    <h3>{{title}} ({{type}})</h3>
                                </div>

                                <div style="margin-top:20px;">
                                    <table width="100%" border="1" cellspacing="0" cellpadding="6">
                                        <tr><td>Total Questions</td><td>{{total_questions}}</td></tr>
                                        <tr><td>Score</td><td>{{score}}</td></tr>
                                        <tr><td>Percentage</td><td>{{percentage}}%</td></tr>
                                        <tr><td>Status</td><td>{{status}}</td></tr>
                                    </table>
                                </div>

                                <div style="margin-top:30px; display:flex; justify-content:space-between;">
                                    <div>
                                        Date: {{date}}<br>
                                        Certificate ID: {{certificate_id}}
                                    </div>
                                    <div style="text-align:center;">
                                        ___________________<br>
                                        {{signer_name}}<br>
                                        {{signer_designation}}
                                    </div>
                                </div>

                                <div style="margin-top:30px; text-align:center;">
                                    {{footer_text}}
                                </div>
                            </div>
                            HTML
        ]);
    }
}
