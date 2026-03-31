<?php

use Illuminate\Support\Facades\Route;
use App\Modules\Admin\Auth\Controllers\AuthController;

use App\Modules\Admin\Program\Controllers\ProgramController;
use App\Modules\Admin\Program\Controllers\LevelController;
use App\Modules\Admin\Program\Controllers\ModuleController;
use App\Modules\Admin\Program\Controllers\ChapterController;
use App\Modules\Admin\Program\Controllers\TopicController;
use App\Modules\Admin\Language\Controllers\LanguageController;
use App\Modules\Admin\UserManagement\Controllers\UserController;


Route::prefix('v1')->group(function () {

    Route::prefix('admin')->group(function () {

        // 🔐 AUTH
        Route::post('/login', [AuthController::class, 'login']);

        Route::middleware(['auth:sanctum', 'role:superadmin,staff'])->group(function () {
            Route::post('/logout', [AuthController::class, 'logout']);
        });

        // 🔒 SUPERADMIN ONLY
        Route::middleware(['auth:sanctum', 'role:superadmin'])->group(function () {
            /*
            |--------------------------------------------------------------------------
            | LANGUAGES
            |--------------------------------------------------------------------------
            */
            Route::get('languages', [LanguageController::class, 'index']);
            Route::post('languages', [LanguageController::class, 'store']);
            Route::get('languages/{id}', [LanguageController::class, 'show']);
            Route::post('languages/{id}', [LanguageController::class, 'update']);
            Route::delete('languages/{id}', [LanguageController::class, 'destroy']);
            Route::post('languages/{id}/toggle-status', [LanguageController::class, 'toggleStatus']);
            /*
            |--------------------------------------------------------------------------
            | USERS MANAGEMENT
            |--------------------------------------------------------------------------
            */
            Route::get('users', [UserController::class, 'index']);
            Route::post('users', [UserController::class, 'store']);
            Route::get('users/{id}', [UserController::class, 'show']);
            Route::post('users/{id}', [UserController::class, 'update']);
            Route::delete('users/{id}', [UserController::class, 'destroy']);
            Route::post('users/{id}/toggle-status', [UserController::class, 'toggleStatus']);
            /*
            |--------------------------------------------------------------------------
            | PROGRAMS
            |--------------------------------------------------------------------------
            */
            Route::get('programs', [ProgramController::class, 'index']);
            Route::post('programs', [ProgramController::class, 'store']);
            Route::get('programs/{id}', [ProgramController::class, 'show']);
            Route::post('programs/{id}', [ProgramController::class, 'update']); // 🔥 POST update
            Route::delete('programs/{id}', [ProgramController::class, 'destroy']);
            Route::post('programs/{id}/toggle-status', [ProgramController::class, 'toggleStatus']);


            /*
            |--------------------------------------------------------------------------
            | LEVELS
            |--------------------------------------------------------------------------
            */
            Route::get('levels', [LevelController::class, 'index']);
            Route::post('levels', [LevelController::class, 'store']);
            Route::get('levels/{id}', [LevelController::class, 'show']);
            Route::post('levels/{id}', [LevelController::class, 'update']);
            Route::delete('levels/{id}', [LevelController::class, 'destroy']);
            Route::post('levels/{id}/toggle-status', [LevelController::class, 'toggleStatus']);


            /*
            |--------------------------------------------------------------------------
            | MODULES
            |--------------------------------------------------------------------------
            */
            Route::get('modules', [ModuleController::class, 'index']);
            Route::post('modules', [ModuleController::class, 'store']);
            Route::get('modules/{id}', [ModuleController::class, 'show']);
            Route::post('modules/{id}', [ModuleController::class, 'update']);
            Route::delete('modules/{id}', [ModuleController::class, 'destroy']);
            Route::post('modules/{id}/toggle-status', [ModuleController::class, 'toggleStatus']);


            /*
            |--------------------------------------------------------------------------
            | CHAPTERS
            |--------------------------------------------------------------------------
            */
            Route::get('chapters', [ChapterController::class, 'index']);
            Route::post('chapters', [ChapterController::class, 'store']);
            Route::get('chapters/{id}', [ChapterController::class, 'show']);
            Route::post('chapters/{id}', [ChapterController::class, 'update']);
            Route::delete('chapters/{id}', [ChapterController::class, 'destroy']);
            Route::post('chapters/{id}/toggle-status', [ChapterController::class, 'toggleStatus']);


            /*
            |--------------------------------------------------------------------------
            | TOPICS
            |--------------------------------------------------------------------------
            */
            Route::get('topics', [TopicController::class, 'index']);
            Route::post('topics', [TopicController::class, 'store']);
            Route::get('topics/{id}', [TopicController::class, 'show']);
            Route::post('topics/{id}', [TopicController::class, 'update']);
            Route::delete('topics/{id}', [TopicController::class, 'destroy']);
            Route::post('topics/{id}/toggle-status', [TopicController::class, 'toggleStatus']);
        });
    });
});
