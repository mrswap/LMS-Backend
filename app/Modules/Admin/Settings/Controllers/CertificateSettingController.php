<?php

namespace App\Modules\Admin\Settings\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\CertificateSetting;
use App\Services\Certificate\CertificateVariableService;

class CertificateSettingController extends Controller
{
    protected $uploadPath = 'uploads/certificates/';

    /*
    |-----------------------------------------
    | GET SETTINGS
    |-----------------------------------------
    */
    public function get()
    {
        $data = CertificateSetting::first();

        return response()->json([
            'status' => true,
            'data' => $data
        ]);
    }

    /*
    |-----------------------------------------
    | UPDATE SETTINGS
    |-----------------------------------------
    */
    public function update(Request $request)
    {
        $data = $request->only([
            'company_name',
            'tagline',
            'certificate_heading',
            'signer_name',
            'signer_designation',
            'content',
            'footer_text'
        ]);

        $setting = CertificateSetting::first();

        /*
        |-----------------------------------------
        | LOGO UPLOAD
        |-----------------------------------------
        */
        if ($request->hasFile('company_logo')) {
            $file = $request->file('company_logo');
            $name = time() . '_logo.' . $file->getClientOriginalExtension();

            $file->move(public_path($this->uploadPath), $name);

            $data['company_logo'] = 'public/' . $this->uploadPath . $name;
        }

        /*
        |-----------------------------------------
        | SIGNATURE UPLOAD
        |-----------------------------------------
        */
        if ($request->hasFile('signer_signature')) {
            $file = $request->file('signer_signature');
            $name = time() . '_sign.' . $file->getClientOriginalExtension();

            $file->move(public_path($this->uploadPath), $name);

            $data['signer_signature'] = 'public/' . $this->uploadPath . $name;
        }

        /*
        |-----------------------------------------
        | SAVE / UPDATE
        |-----------------------------------------
        */
        $setting = CertificateSetting::updateOrCreate(
            ['id' => $setting->id ?? null],
            $data
        );

        return response()->json([
            'status' => true,
            'message' => 'Certificate settings updated successfully',
            'data' => $setting
        ]);
    }

    /*
    |-----------------------------------------
    | VARIABLES LIST (ADMIN UI)
    |-----------------------------------------
    */
    public function variables()
    {
        return response()->json([
            'status' => true,
            'data' => CertificateVariableService::get()
        ]);
    }
}
