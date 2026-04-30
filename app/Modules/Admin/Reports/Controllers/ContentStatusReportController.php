<?php

namespace App\Modules\Admin\Reports\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\Reports\ContentStatusReportService;

class ContentStatusReportController extends Controller
{
    public function index(Request $request)
    {
        $data = (new ContentStatusReportService())->getReport($request);

        return response()->json([
            'status' => true,
            'message' => 'Content Status Report fetched successfully',
            'data' => $data
        ]);
    }
}