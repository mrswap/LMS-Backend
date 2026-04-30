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
use App\Modules\Admin\Auth\Controllers\PasswordController;
use App\Modules\Admin\ContentManagement\Controllers\MediaController;
use App\Modules\Admin\ContentManagement\Controllers\SectionContentController;
use App\Modules\Admin\Settings\Controllers\RoleController;
use App\Modules\Admin\Settings\Controllers\DesignationController;
use App\Modules\Admin\Settings\Controllers\SmtpController;
use App\Modules\Admin\FAQ\Controllers\FaqController;
use App\Modules\Admin\Assessment\Controllers\AssessmentController;
use App\Modules\Admin\Assessment\Controllers\QuestionController;
use App\Modules\Admin\Assessment\Controllers\OptionController;
use App\Modules\Admin\Assessment\Controllers\FeedbackController;
use App\Modules\Admin\Settings\Controllers\SiteSettingController;
use App\Modules\Admin\Contact\Controllers\ContactController;
use App\Modules\Admin\Reports\Controllers\AuditReportController;

Route::prefix('v1')->group(function () {

    Route::prefix('admin')->group(function () {

        // 🔐 AUTH
        Route::post('/login', [AuthController::class, 'login']);
        // PASSWORD RESET
        Route::post('/forgot-password', [PasswordController::class, 'forgotPassword']);
        Route::post('/reset-password', [PasswordController::class, 'resetPassword']);

        Route::middleware(['auth:sanctum', 'role:superadmin,staff'])->group(function () {
            Route::post('/logout', [AuthController::class, 'logout']);

            //profile 
            Route::get('/profile', [UserController::class, 'profile']);
            Route::post('/profile', [UserController::class, 'updateProfile']);
            Route::post('change-password', [UserController::class, 'changePassword']);
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
            Route::post('/users/{id}/reset-device', [UserController::class, 'resetDevice']);
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

            /*
            |--------------------------------------------------------------------------
            | FAQs
            |--------------------------------------------------------------------------
            */
            Route::get('faqs', [FaqController::class, 'index']);
            Route::post('faqs', [FaqController::class, 'store']);
            Route::get('faqs/{id}', [FaqController::class, 'show']);
            Route::post('faqs/{id}', [FaqController::class, 'update']);
            Route::delete('faqs/{id}', [FaqController::class, 'destroy']);
            Route::post('faqs/{id}/toggle-status', [FaqController::class, 'toggleStatus']);
            /*
            |--------------------------------------------------------------------------
            | MEDIA LIBRARY
            |--------------------------------------------------------------------------
            */

            Route::post('media', [MediaController::class, 'store']);
            Route::get('media', [MediaController::class, 'index']);
            Route::get('media/{id}', [MediaController::class, 'show']);
            Route::post('media/{id}', [MediaController::class, 'update']);
            Route::delete('media/{id}', [MediaController::class, 'destroy']);
            Route::post('media/{id}/toggle-status', [MediaController::class, 'toggleStatus']);

            /*
            |--------------------------------------------------------------------------
            | Section Content
            |--------------------------------------------------------------------------
            */

            // 🔹 CREATE
            Route::post('content-topics/{topic_id}/contents', [SectionContentController::class, 'store']);
            // 🔹 LIST
            Route::get('content-topics/{topic_id}/contents', [SectionContentController::class, 'index']);
            // 🔹 FULL (frontend)
            Route::get('content-topics/{topic_id}/full', [SectionContentController::class, 'full']);
            Route::get('contents', [SectionContentController::class, 'index']);
            // 🔥 IMPORTANT: specific routes BEFORE {id}
            // 🔹 REORDER (must be before {id})
            Route::post('content-topics/{topic_id}/contents/reorder', [SectionContentController::class, 'reorder']);
            // 🔹 TOGGLE STATUS
            Route::post('content-topics/{topic_id}/contents/{id}/toggle-status', [SectionContentController::class, 'toggleStatus'])->whereNumber('id');
            // 🔹 SINGLE
            Route::get('content-topics/{topic_id}/contents/{id}', [SectionContentController::class, 'show'])->whereNumber('id');
            // 🔹 UPDATE
            Route::post('content-topics/{topic_id}/contents/{id}', [SectionContentController::class, 'update'])->whereNumber('id');
            // 🔹 DELETE
            Route::delete('content-topics/{topic_id}/contents/{id}', [SectionContentController::class, 'destroy'])->whereNumber('id');
            Route::post('content-topics/{topic_id}/contents/bulk', [SectionContentController::class, 'bulkStore']);


            /*
            |--------------------------------------------------------------------------
            | Assesment  Content
            |--------------------------------------------------------------------------
            */
            Route::prefix('assessments')->group(function () {

                Route::get('assessment-feedbacks', [FeedbackController::class, 'index']);
                Route::get('assessment-feedbacks/{id}', [FeedbackController::class, 'show']);

                Route::get('/', [AssessmentController::class, 'index']);
                Route::post('/', [AssessmentController::class, 'store']);
                Route::get('{id}', [AssessmentController::class, 'show']);
                Route::post('{id}', [AssessmentController::class, 'update']);
                Route::delete('{id}', [AssessmentController::class, 'destroy']);
                Route::post('{id}/toggle-status', [AssessmentController::class, 'toggleStatus']);

                Route::post('{assessment_id}/questions', [QuestionController::class, 'store']);
                Route::get('{assessment_id}/questions', [QuestionController::class, 'index']);
                Route::post('questions/{id}', [QuestionController::class, 'update']);
                Route::delete('questions/{id}', [QuestionController::class, 'destroy']);
                Route::get('questions/{id}', [QuestionController::class, 'show']);

                Route::post('questions/{question_id}/options', [OptionController::class, 'store']);
                Route::post('options/{id}', [OptionController::class, 'update']);
                Route::delete('options/{id}', [OptionController::class, 'destroy']);
                Route::get('questions/{question_id}/options', [OptionController::class, 'index']);
                Route::get('options/{id}', [OptionController::class, 'show']);
            });




            /*
            |--------------------------------------------------------------------------
            | Settings - Roles,
            |--------------------------------------------------------------------------
            */
            Route::prefix('setting')->group(function () {
                //setting
                Route::get('roles', [RoleController::class, 'index']);
                Route::post('roles', [RoleController::class, 'store']);
                Route::get('roles/{id}', [RoleController::class, 'show']);
                Route::post('roles/{id}', [RoleController::class, 'update']);
                Route::delete('roles/{id}', [RoleController::class, 'destroy']);
                Route::post('roles/{id}/toggle-status', [RoleController::class, 'toggleStatus']);
                //designation
                Route::get('designations', [DesignationController::class, 'index']);
                Route::post('designations', [DesignationController::class, 'store']);
                Route::get('designations/{id}', [DesignationController::class, 'show']);
                Route::post('designations/{id}', [DesignationController::class, 'update']);
                Route::delete('designations/{id}', [DesignationController::class, 'destroy']);
                Route::post('designations/{id}/toggle-status', [DesignationController::class, 'toggleStatus']);
                //smtp
                Route::get('smtp', [SmtpController::class, 'get']);
                Route::post('smtp', [SmtpController::class, 'update']);
                Route::post('smtp/test', [SmtpController::class, 'test']);
                //site settings
                Route::get('site', [SiteSettingController::class, 'get']);
                Route::post('site', [SiteSettingController::class, 'update']);
                //contact messages\
                Route::get('contacts', [ContactController::class, 'index']);
                Route::get('contacts/{id}', [ContactController::class, 'show']);
                Route::post('contacts/{id}/mark-seen', [ContactController::class, 'markSeen']);
                Route::post('contacts/{id}/mark-unseen', [ContactController::class, 'markUnseen']);
            });


            Route::prefix('reports')->group(function () {
                Route::get('/audit-logs', [AuditReportController::class, 'index']);
            });
        });
    });
});
