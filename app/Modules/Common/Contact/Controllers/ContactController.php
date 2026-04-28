<?php

namespace App\Modules\Common\Contact\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ContactMessage;

class ContactController extends Controller
{
    public function submit(Request $request)
    {
        $request->validate([
            'name'    => 'required|string|max:255',
            'email'   => 'required|email|max:255',
            'subject' => 'nullable|string|max:255',
            'message' => 'required|string'
        ]);

        ContactMessage::create($request->only([
            'name',
            'email',
            'subject',
            'message'
        ]));

        return response()->json([
            'status' => true,
            'message' => 'Message submitted successfully'
        ]);
    }
}
