<?php

namespace App\Modules\Trainee\Reports\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\Reports\UserProgressReportService;

class UserProgressReportController extends Controller
{
    public function index(Request $request)
    {
        $userId = auth()->id();

        $data = (new UserProgressReportService())
            ->getReport($request, $userId);

        return response()->json([
            'status' => true,
            'message' => 'Your progress fetched successfully',
            'data' => $data
        ]);
    }
}
