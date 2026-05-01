<?php

namespace App\Modules\Trainee\Reports\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\Reports\CertificationReportService;
use App\Models\Certification;
use App\Models\CertificateSetting;
use App\Services\Certificate\CertificateRenderService;



class CertificationReportController extends Controller
{
    public function index(Request $request)
    {
        $userId = auth()->id();

        $data = (new CertificationReportService())
            ->getReport($request, $userId);

        return response()->json([
            'status' => true,
            'message' => 'Your certifications fetched successfully',
            'data' => $data
        ]);
    }

    /*
    |--------------------------------------------------
    | 🎓 GET CERTIFICATE BY ATTEMPT
    |--------------------------------------------------
    */
    public function show($attemptId)
    {
        $userId = auth()->id();

        $certificate = Certification::where('assessment_attempt_id', $attemptId)
            ->where('user_id', $userId)
            ->first();

        if (!$certificate) {
            return response()->json([
                'status' => false,
                'message' => 'Certificate not found'
            ], 404);
        }

        /*
        |--------------------------------------------------
        | ⚙️ SETTINGS
        |--------------------------------------------------
        */
        $setting = CertificateSetting::first();

        /*
        |--------------------------------------------------
        | 🔥 RENDER CONTENT
        |--------------------------------------------------
        */
        $renderedContent = CertificateRenderService::render(
            $setting->content,
            $certificate->meta,
            $certificate
        );

        /*
        |--------------------------------------------------
        | 🔗 SHARE LINKS
        |--------------------------------------------------
        */
        $shareText = urlencode("I have successfully completed {$certificate->meta['context']['title']} 🎓");

        $shareUrl = url("/certificate/{$certificate->certificate_id}");

        $shareLinks = [
            'whatsapp' => "https://wa.me/?text={$shareText}%20{$shareUrl}",
            'facebook' => "https://www.facebook.com/sharer/sharer.php?u={$shareUrl}",
            'linkedin' => "https://www.linkedin.com/sharing/share-offsite/?url={$shareUrl}",
        ];

        /*
        |--------------------------------------------------
        | 📦 RESPONSE
        |--------------------------------------------------
        */
        return response()->json([
            'status' => true,

            'data' => [

                // 🧾 certificate basic
                'certificate_id' => $certificate->certificate_id,
                'issued_at' => $certificate->issued_at,

                // 🧠 rendered content
                'content' => $renderedContent,

                // 🎨 design settings
                'design' => [
                    'company_name' => $setting->company_name,
                    'company_logo' => $setting->company_logo_url,
                    'tagline' => $setting->tagline,
                    'heading' => $setting->certificate_heading,

                    'signer_name' => $setting->signer_name,
                    'signer_designation' => $setting->signer_designation,
                    'signer_signature' => $setting->signer_signature_url,

                    'footer_text' => $setting->footer_text,
                ],

                // 📊 meta (optional but useful)
                'meta' => $certificate->meta,

                // 🔗 share
                'share_links' => $shareLinks,
            ]
        ]);
    }
}
