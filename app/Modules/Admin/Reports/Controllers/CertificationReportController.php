<?php

namespace App\Modules\Admin\Reports\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\Reports\CertificationReportService;

class CertificationReportController extends Controller
{
    public function index(Request $request)
    {
        $data = (new CertificationReportService())->getReport($request);

        return response()->json([
            'status' => true,
            'message' => 'Certification Report fetched successfully',
            'data' => $data
        ]);
    }
}
