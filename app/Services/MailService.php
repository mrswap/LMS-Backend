<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use App\Models\SmtpSetting;

class MailService
{
    protected $smtp;

    public function __construct(SmtpService $smtp)
    {
        $this->smtp = $smtp;
    }

    public function send($email, $content)
    {
        try {

            // 🔁 Load SMTP settings (cached)
            $smtp = cache()->remember('smtp_settings', 60, fn() => SmtpSetting::first());

            if ($smtp) {
                $this->smtp->applyConfig($smtp);
            }

            // 🧠 Normalize content
            $subject = $content['subject'] ?? $content['title'] ?? 'Notification';
            $title   = $content['title'] ?? $subject;
            $message = $content['message'] ?? '';
            $link    = $content['link'] ?? null;
            $image   = $content['image'] ?? null;

            // 🎨 Clean HTML template
            $html = $this->buildTemplate($title, $message, $link, $image);

            Mail::html($html, function ($mail) use ($email, $subject) {
                $mail->to($email)->subject($subject);
            });
        } catch (\Throwable $e) {

            Log::error('Email failed', [
                'email' => $email,
                'error' => $e->getMessage()
            ]);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | 📧 Email Template (Reusable)
    |--------------------------------------------------------------------------
    */
    private function buildTemplate($title, $message, $link = null, $image = null)
    {
        return "
        <div style='font-family: Arial, sans-serif; padding:20px; background:#f9f9f9'>
            <div style='max-width:600px; margin:auto; background:#ffffff; padding:20px; border-radius:8px'>

                <h2 style='color:#333'>{$title}</h2>

                <p style='color:#555; line-height:1.6'>
                    {$message}
                </p>

                " . ($link ? "
                    <p>
                        <a href='{$link}' 
                           style='display:inline-block; padding:10px 15px; background:#007bff; color:#fff; text-decoration:none; border-radius:5px'>
                           Open
                        </a>
                    </p>
                " : "") . "

                " . ($image ? "
                    <p>
                        <img src='{$image}' style='max-width:100%; border-radius:6px'/>
                    </p>
                " : "") . "

                <hr style='margin-top:30px'/>

                <p style='font-size:12px; color:#999'>
                    This is an automated message. Please do not reply.
                </p>

            </div>
        </div>
        ";
    }
}
