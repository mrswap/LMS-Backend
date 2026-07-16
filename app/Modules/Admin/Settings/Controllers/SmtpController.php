<?php

namespace App\Modules\Admin\Settings\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\SmtpService;
use Illuminate\Support\Facades\Mail;
use App\Services\MailService;

class SmtpController extends Controller {
    protected $service;
    protected $mailService;

    public function __construct(
        SmtpService $service,
        MailService $mailService
    ) {
        $this->service = $service;
        $this->mailService = $mailService;
    }
    // GET
    public function get() {
        return response()->json([
            'data' => $this->service->get()
        ]);
    }

    // UPDATE
    public function update(Request $request) {
        $request->validate([
            'host' => 'required',
            'port' => 'required',
            'username' => 'required',
            'encryption' => 'required',
            'from_address' => 'required|email',
            'from_name' => 'required',
        ]);

        $smtp = $this->service->update($request->all());

        return response()->json([
            'message' => 'SMTP updated successfully',
            'data' => $smtp
        ]);
    }

    public function test(Request $request) {
        $request->validate([
            'email' => 'required|email',
        ]);

        $this->mailService->send($request->email, [
            'subject' => 'SMTP Test',
            'title' => 'SMTP Test',
            'message' => 'SMTP Test Successful',
        ]);

        return response()->json([
            'message' => 'Test email sent',
        ]);
    }
}
