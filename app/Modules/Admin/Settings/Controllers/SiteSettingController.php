<?php

namespace App\Modules\Admin\Settings\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Setting;

class SiteSettingController extends Controller
{
    protected $uploadPath = 'uploads/settings/';

    public function get()
    {
        $keys = [
            'company_logo',
            'company_bio',
            'app_ios_store',
            'app_ios_download',
            'app_android_store',
            'app_android_download',
            'contact_heading',
            'contact_text',
            'contact_phone',
            'contact_email',
            'social_facebook',
            'social_linkedin',
            'social_instagram',
            'social_twitter',
            'footer_text',
            'about_us',
            'privacy_policy',
            'terms_conditions'
        ];

        $data = [];

        foreach ($keys as $key) {

            if ($key == 'company_logo') {
                $data[$key] = Setting::getFullUrl($key);
            } else {
                $data[$key] = Setting::getValue($key);
            }
        }
        return response()->json([
            'status' => true,
            'data' => $data
        ]);
    }

    public function update(Request $request)
    {
        // 🔹 FILE UPLOAD (LOGO)
        if ($request->hasFile('company_logo')) {
            $file = $request->file('company_logo');
            $name = time() . '_' . $file->getClientOriginalName();
            $file->move(public_path($this->uploadPath), $name);

            Setting::setValue('company_logo', 'public/' . $this->uploadPath . $name);
        }

        // 🔹 TEXT SETTINGS
        $fields = [
            'company_bio',
            'app_ios_store',
            'app_ios_download',
            'app_android_store',
            'app_android_download',
            'contact_heading',
            'contact_text',
            'contact_phone',
            'contact_email',
            'social_facebook',
            'social_linkedin',
            'social_instagram',
            'social_twitter',
            'footer_text',
            'about_us',
            'privacy_policy',
            'terms_conditions'
        ];

        foreach ($fields as $field) {
            if ($request->has($field)) {
                Setting::setValue($field, $request->$field);
            }
        }

        return response()->json([
            'status' => true,
            'message' => 'Settings updated successfully'
        ]);
    }
}
