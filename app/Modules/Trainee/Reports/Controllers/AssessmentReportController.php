<?php

namespace App\Modules\Trainee\Reports\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\Reports\AssessmentReportService;

class AssessmentReportController extends Controller
{
    public function index(Request $request)
    {
        $userId = auth()->id();

        $data = (new AssessmentReportService())
            ->getReport($request, $userId);

        return response()->json([
            'status' => true,
            'message' => 'Your assessment report fetched successfully',
            'data' => $data
        ]);
    }
}
