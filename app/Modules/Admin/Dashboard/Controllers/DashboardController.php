<?php

namespace App\Modules\Admin\Dashboard\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Admin\Dashboard\Services\DashboardService;

class DashboardController extends Controller
{
    protected $service;

    public function __construct(DashboardService $service)
    {
        $this->service = $service;
    }

    public function index()
    {
        return response()->json(
            $this->service->getDashboard()
        );
    }
}