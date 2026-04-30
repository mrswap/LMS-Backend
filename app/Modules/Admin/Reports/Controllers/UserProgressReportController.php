<?php

namespace App\Modules\Admin\Reports\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\Reports\UserProgressReportService;

class UserProgressReportController extends Controller
{
    public function index(Request $request)
    {
        $data = (new UserProgressReportService())->getReport($request);

        return response()->json([
            'status' => true,
            'message' => 'User Progress Report fetched successfully',
            'data' => $data
        ]);
    }
}
