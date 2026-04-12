<?php
namespace App\Modules\Admin\Settings\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\SmtpService;
use Illuminate\Support\Facades\Mail;

class SmtpController extends Controller
{
    protected $service;

    public function __construct(SmtpService $service)
    {
        $this->service = $service;
    }

    // GET
    public function get()
    {
        return response()->json([
            'data' => $this->service->get()
        ]);
    }

    // UPDATE
    public function update(Request $request)
    {
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

    // TEST MAIL
    public function test(Request $request)
    {
        $request->validate([
            'email' => 'required|email'
        ]);

        Mail::raw('SMTP Test Successful', function ($message) use ($request) {
            $message->to($request->email)   
                    ->subject('SMTP Test');
        });

        return response()->json([
            'message' => 'Test email sent'
        ]);
    }
}