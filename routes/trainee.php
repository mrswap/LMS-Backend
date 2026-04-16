<?php

use Illuminate\Support\Facades\Route;
use App\Modules\Trainee\Auth\Controllers\AuthController;
use App\Modules\Trainee\Auth\Controllers\PasswordController;
use App\Modules\Trainee\Profile\Controllers\ProfileController;

Route::prefix('v1/trainee')->group(function () {

    /*
    |--------------------------------------------------
    | 🔓 PUBLIC ROUTES
    |--------------------------------------------------
    */
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);

    Route::post('/forgot-password', [PasswordController::class, 'forgotPassword']);
    Route::post('/reset-password', [PasswordController::class, 'resetPassword']);
    Route::get('/verify-email', [AuthController::class, 'verifyEmail']);
    /*
    |--------------------------------------------------
    | 🔐 PROTECTED (ONLY TRAINEE / SALES)
    |--------------------------------------------------
    */
    Route::middleware(['auth:sanctum', 'role:sales'])->group(function () {

        Route::post('/logout', [AuthController::class, 'logout']);

        Route::get('/profile', [ProfileController::class, 'profile']);
        Route::post('/update-profile', [ProfileController::class, 'updateProfile']);
        Route::post('/change-password', [ProfileController::class, 'changePassword']);
    });
});
