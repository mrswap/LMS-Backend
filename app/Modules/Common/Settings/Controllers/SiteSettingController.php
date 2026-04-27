<?php

namespace App\Modules\Common\Settings\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Setting;

class SiteSettingController extends Controller
{
    public function get()
    {
        return response()->json([
            'status' => true,
            'data' => Setting::getAllFormatted()
        ]);
    }
}