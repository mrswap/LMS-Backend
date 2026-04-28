<?php

use Illuminate\Support\Facades\Route;
use App\Modules\Trainee\Auth\Controllers\AuthController;
use App\Modules\Trainee\Auth\Controllers\PasswordController;
use App\Modules\Trainee\Profile\Controllers\ProfileController;
use App\Modules\Trainee\Assessment\Controllers\AttemptController;
use App\Modules\Trainee\Progress\Controllers\ProgressController;
use App\Modules\Trainee\Content\Controllers\ContentController;
use App\Modules\Trainee\Content\Controllers\ContentProgressController;
use App\Modules\Trainee\Assessment\Controllers\FeedbackController;
use App\Modules\Trainee\FAQ\Controllers\FaqController;


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


        Route::prefix('assessments')->group(function () {
            Route::post('{id}/start', [AttemptController::class, 'start']);
            Route::get('{id}/questions', [AttemptController::class, 'questions']);
            Route::post('answer', [AttemptController::class, 'answer']);
            Route::get('{id}/resume', [AttemptController::class, 'resume']);
            Route::post('{id}/submit', [AttemptController::class, 'submit']);
            Route::post('{id}/feedback', [FeedbackController::class, 'store']);
        });

        Route::get('/progress', [ProgressController::class, 'index']);
        Route::get('/hierarchy', [ProgressController::class, 'hierarchy']);
        Route::get('/hierarchy/{type}/{id}', [ProgressController::class, 'single']);
        Route::get('/faqs/{type}/{id}', [FaqController::class, 'single']);

        Route::prefix('content')->group(function () {
            Route::get('topics/{topic_id}', [ContentController::class, 'index']);
            Route::post('{id}/toggle-read', [ContentProgressController::class, 'toggle']);
            Route::get('single-preview/{topic_id}/{content_id}', [ContentController::class, 'single']);
        });
    });
});
