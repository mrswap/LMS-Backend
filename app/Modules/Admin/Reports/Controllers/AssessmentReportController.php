<?php

namespace App\Modules\Admin\Reports\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\Reports\AssessmentReportService;

class AssessmentReportController extends Controller
{
    public function index(Request $request)
    {
        $data = (new AssessmentReportService())->getReport($request);

        return response()->json([
            'status' => true,
            'message' => 'Assessment Report fetched successfully',
            'data' => $data
        ]);
    }
}