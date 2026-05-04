<?php

namespace App\Modules\Trainee\Dashboard\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Trainee\Dashboard\Services\DashboardService;

class DashboardController extends Controller
{
    public function index()
    {
        $userId = auth()->id();

        $data = (new DashboardService())->getDashboard($userId);

        return response()->json([
            'status' => true,
            'message' => 'Dashboard data fetched',
            'data' => $data
        ]);
    }
}
