<?php

namespace App\Services;

use App\Models\SmtpSetting;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;

class MailService {
    public function send($email, $content) {
        try {

            // SMTP from DB (cached)
            $smtp = cache()->remember('smtp_settings', 60, function () {
                return SmtpSetting::first();
            });

            // Normalize
            $subject = $content['subject'] ?? $content['title'] ?? 'Notification';
            $title   = $content['title'] ?? $subject;
            $message = $content['message'] ?? '';
            $link    = $content['link'] ?? null;
            $image   = $content['image'] ?? null;

            $html = $this->buildTemplate(
                $title,
                $message,
                $link,
                $image
            );

            /*
            |--------------------------------------------------------------------------
            | DB SMTP
            |--------------------------------------------------------------------------
            */

            if ($smtp) {

                $transport = new EsmtpTransport(
                    $smtp->host,
                    (int) $smtp->port,
                    strtolower($smtp->encryption) === 'ssl'
                );

                $transport->setUsername($smtp->username);
                $transport->setPassword(decrypt($smtp->password));

                $mailer = new Mailer($transport);

                $mail = (new Email())
                    ->from(sprintf('%s <%s>', $smtp->from_name, $smtp->from_address))
                    ->to($email)
                    ->subject($subject)
                    ->html($html);

                $mailer->send($mail);

                Log::info('Mail sent using DB SMTP', [
                    'to' => $email,
                    'host' => $smtp->host,
                ]);

                return true;
            }

            /*
            |--------------------------------------------------------------------------
            | ENV SMTP Fallback
            |--------------------------------------------------------------------------
            */

            Mail::html($html, function ($mail) use ($email, $subject) {

                $mail->to($email)
                    ->subject($subject);
            });

            Log::info('Mail sent using ENV SMTP', [
                'to' => $email,
            ]);

            return true;
        } catch (\Throwable $e) {

            Log::error('Mail sending failed', [
                'email' => $email,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            /*
            |--------------------------------------------------------------------------
            | Fallback to ENV if DB SMTP fails
            |--------------------------------------------------------------------------
            */

            try {

                Mail::html(
                    $this->buildTemplate(
                        $content['title'] ?? 'Notification',
                        $content['message'] ?? '',
                        $content['link'] ?? null,
                        $content['image'] ?? null
                    ),
                    function ($mail) use ($email, $subject) {
                        $mail->to($email)
                            ->subject($subject);
                    }
                );

                Log::warning('Fallback ENV SMTP used', [
                    'email' => $email,
                ]);

                return true;
            } catch (\Throwable $e2) {

                Log::error('ENV SMTP also failed', [
                    'email' => $email,
                    'message' => $e2->getMessage(),
                ]);

                return false;
            }
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Email Template
    |--------------------------------------------------------------------------
    */

    private function buildTemplate($title, $message, $link = null, $image = null) {
        return "
        <div style='font-family:Arial,sans-serif;padding:20px;background:#f5f5f5'>
            <div style='max-width:600px;margin:auto;background:#fff;padding:30px;border-radius:8px'>

                <h2>{$title}</h2>

                <p style='line-height:1.7'>
                    {$message}
                </p>

                " . ($link ? "
                    <p>
                        <a href='{$link}'
                           style='background:#2563eb;color:#fff;padding:12px 20px;text-decoration:none;border-radius:6px'>
                           Open
                        </a>
                    </p>
                " : "") . "

                " . ($image ? "
                    <p>
                        <img src='{$image}' style='max-width:100%'>
                    </p>
                " : "") . "

                <hr>

                <small>
                    This is an automated email.
                </small>

            </div>
        </div>
        ";
    }
}
