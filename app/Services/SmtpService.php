<?php
namespace App\Services;

use App\Models\SmtpSetting;
use Illuminate\Support\Facades\Config;

class SmtpService
{
    public function get()
    {
        $smtp = SmtpSetting::first();

        if (!$smtp) {
            return [
                'mailer' => config('mail.default'),
                'host' => config('mail.mailers.smtp.host'),
                'port' => config('mail.mailers.smtp.port'),
                'username' => config('mail.mailers.smtp.username'),
                'password' => null, // never expose
                'encryption' => config('mail.mailers.smtp.encryption'),
                'from_address' => config('mail.from.address'),
                'from_name' => config('mail.from.name'),
            ];
        }

        return $smtp;
    }

    public function update(array $data)
    {
        $smtp = SmtpSetting::first();

        if (!$smtp) {
            $smtp = new SmtpSetting();
        }

        // 🔐 encrypt password
        if (isset($data['password'])) {
            $data['password'] = encrypt($data['password']);
        }

        $smtp->fill($data);
        $smtp->save();

        $this->applyConfig($smtp);

        return $smtp;
    }

    public function applyConfig($smtp)
    {
        Config::set('mail.default', $smtp->mailer);

        Config::set('mail.mailers.smtp.host', $smtp->host);
        Config::set('mail.mailers.smtp.port', $smtp->port);
        Config::set('mail.mailers.smtp.username', $smtp->username);
        Config::set('mail.mailers.smtp.password', decrypt($smtp->password));
        Config::set('mail.mailers.smtp.encryption', $smtp->encryption);

        
        Config::set('mail.from.address', $smtp->from_address);
        Config::set('mail.from.name', $smtp->from_name);
    }
}