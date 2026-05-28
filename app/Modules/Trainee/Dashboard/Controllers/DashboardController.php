<?php

namespace App\Modules\Trainee\Dashboard\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Trainee\Dashboard\Services\DashboardService;

class DashboardController extends Controller
{
    protected $dashboardService;

    public function __construct(
        DashboardService $dashboardService
    ) {
        $this->dashboardService = $dashboardService;
    }

    public function index()
    {
        $userId = auth()->id();

        $data = $this->dashboardService
            ->getDashboard($userId);

        return response()->json([

            'status' => true,

            'message' => 'Dashboard data fetched',

            'data' => $data
        ]);
    }
}