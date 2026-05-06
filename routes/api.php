<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| AUTH
|--------------------------------------------------------------------------
*/

use App\Modules\Admin\Auth\Controllers\AuthController;
use App\Modules\Admin\Auth\Controllers\PasswordController;

/*
|--------------------------------------------------------------------------
| DASHBOARD
|--------------------------------------------------------------------------
*/

use App\Modules\Admin\Dashboard\Controllers\DashboardController;

/*
|--------------------------------------------------------------------------
| PROGRAM
|--------------------------------------------------------------------------
*/

use App\Modules\Admin\Program\Controllers\ProgramController;
use App\Modules\Admin\Program\Controllers\LevelController;
use App\Modules\Admin\Program\Controllers\ModuleController;
use App\Modules\Admin\Program\Controllers\ChapterController;
use App\Modules\Admin\Program\Controllers\TopicController;

/*
|--------------------------------------------------------------------------
| LANGUAGE
|--------------------------------------------------------------------------
*/

use App\Modules\Admin\Language\Controllers\LanguageController;

/*
|--------------------------------------------------------------------------
| USERS
|--------------------------------------------------------------------------
*/

use App\Modules\Admin\UserManagement\Controllers\UserController;

/*
|--------------------------------------------------------------------------
| CONTENT
|--------------------------------------------------------------------------
*/

use App\Modules\Admin\ContentManagement\Controllers\MediaController;
use App\Modules\Admin\ContentManagement\Controllers\SectionContentController;

/*
|--------------------------------------------------------------------------
| SETTINGS
|--------------------------------------------------------------------------
*/

use App\Modules\Admin\Settings\Controllers\RoleController;
use App\Modules\Admin\Settings\Controllers\PermissionController;
use App\Modules\Admin\Settings\Controllers\DesignationController;
use App\Modules\Admin\Settings\Controllers\SmtpController;
use App\Modules\Admin\Settings\Controllers\SiteSettingController;
use App\Modules\Admin\Settings\Controllers\CertificateSettingController;

/*
|--------------------------------------------------------------------------
| FAQ
|--------------------------------------------------------------------------
*/

use App\Modules\Admin\FAQ\Controllers\FaqController;

/*
|--------------------------------------------------------------------------
| ASSESSMENTS
|--------------------------------------------------------------------------
*/

use App\Modules\Admin\Assessment\Controllers\AssessmentController;
use App\Modules\Admin\Assessment\Controllers\QuestionController;
use App\Modules\Admin\Assessment\Controllers\OptionController;
use App\Modules\Admin\Assessment\Controllers\FeedbackController;

/*
|--------------------------------------------------------------------------
| CONTACTS
|--------------------------------------------------------------------------
*/

use App\Modules\Admin\Contact\Controllers\ContactController;

/*
|--------------------------------------------------------------------------
| REPORTS
|--------------------------------------------------------------------------
*/

use App\Modules\Admin\Reports\Controllers\AuditReportController;
use App\Modules\Admin\Reports\Controllers\UserProgressReportController;
use App\Modules\Admin\Reports\Controllers\AssessmentReportController;
use App\Modules\Admin\Reports\Controllers\ContentStatusReportController;
use App\Modules\Admin\Reports\Controllers\CertificationReportController;

/*
|--------------------------------------------------------------------------
| COMMON
|--------------------------------------------------------------------------
*/

use App\Modules\Admin\CommonPublishStatusController;

/*
|--------------------------------------------------------------------------
| ROUTES
|--------------------------------------------------------------------------
*/

Route::prefix('v1/admin')->group(function () {

    /*
    |--------------------------------------------------------------------------
    | PUBLIC ROUTES
    |--------------------------------------------------------------------------
    */

    Route::post('/login', [AuthController::class, 'login']);

    Route::post('/forgot-password', [PasswordController::class, 'forgotPassword']);

    Route::post('/reset-password', [PasswordController::class, 'resetPassword']);

    /*
    |--------------------------------------------------------------------------
    | AUTHENTICATED ROUTES
    |--------------------------------------------------------------------------
    */

    Route::middleware(['auth:sanctum'])->group(function () {

        /*
        |--------------------------------------------------------------------------
        | PROFILE
        |--------------------------------------------------------------------------
        */

        Route::post('/logout', [AuthController::class, 'logout']);

        Route::get('/profile', [UserController::class, 'profile']);

        Route::post('/profile', [UserController::class, 'updateProfile']);

        Route::post('/change-password', [UserController::class, 'changePassword']);

        /*
        |--------------------------------------------------------------------------
        | DASHBOARD
        |--------------------------------------------------------------------------
        */

        Route::get('/dashboard', [DashboardController::class, 'index']);

        /*
        |--------------------------------------------------------------------------
        | PUBLISH STATUS
        |--------------------------------------------------------------------------
        */

        Route::post('publish-status/update', [CommonPublishStatusController::class, 'update'])->middleware('permission:content.edit');

        /*
        |--------------------------------------------------------------------------
        | LANGUAGES
        |--------------------------------------------------------------------------
        */

        Route::get('languages', [LanguageController::class, 'index'])->middleware('permission:languages.view');

        Route::post('languages', [LanguageController::class, 'store'])->middleware('permission:languages.create');

        Route::get('languages/{id}', [LanguageController::class, 'show'])->middleware('permission:languages.view');

        Route::post('languages/{id}', [LanguageController::class, 'update'])->middleware('permission:languages.edit');

        Route::delete('languages/{id}', [LanguageController::class, 'destroy'])->middleware('permission:languages.delete');

        Route::post('languages/{id}/toggle-status', [LanguageController::class, 'toggleStatus'])->middleware('permission:languages.status');

        /*
        |--------------------------------------------------------------------------
        | USERS
        |--------------------------------------------------------------------------
        */

        Route::get('users', [UserController::class, 'index'])->middleware('permission:users.view');

        Route::post('users', [UserController::class, 'store'])->middleware('permission:users.create');

        Route::get('users/{id}', [UserController::class, 'show'])->middleware('permission:users.view');

        Route::post('users/{id}', [UserController::class, 'update'])->middleware('permission:users.edit');

        Route::delete('users/{id}', [UserController::class, 'destroy'])->middleware('permission:users.delete');

        Route::post('users/{id}/toggle-status', [UserController::class, 'toggleStatus'])->middleware('permission:users.status');

        Route::post('users/{id}/reset-device', [UserController::class, 'resetDevice'])->middleware('permission:users.reset-device');

        /*
        |--------------------------------------------------------------------------
        | PROGRAMS
        |--------------------------------------------------------------------------
        */

        Route::get('programs', [ProgramController::class, 'index'])->middleware('permission:programs.view');

        Route::post('programs', [ProgramController::class, 'store'])->middleware('permission:programs.create');

        Route::get('programs/{id}', [ProgramController::class, 'show'])->middleware('permission:programs.view');

        Route::post('programs/{id}', [ProgramController::class, 'update'])->middleware('permission:programs.edit');

        Route::delete('programs/{id}', [ProgramController::class, 'destroy'])->middleware('permission:programs.delete');

        Route::post('programs/{id}/toggle-status', [ProgramController::class, 'toggleStatus'])->middleware('permission:programs.status');

        /*
        |--------------------------------------------------------------------------
        | LEVELS
        |--------------------------------------------------------------------------
        */

        Route::get('levels', [LevelController::class, 'index'])->middleware('permission:levels.view');

        Route::post('levels', [LevelController::class, 'store'])->middleware('permission:levels.create');

        Route::get('levels/{id}', [LevelController::class, 'show'])->middleware('permission:levels.view');

        Route::post('levels/{id}', [LevelController::class, 'update'])->middleware('permission:levels.edit');

        Route::delete('levels/{id}', [LevelController::class, 'destroy'])->middleware('permission:levels.delete');

        Route::post('levels/{id}/toggle-status', [LevelController::class, 'toggleStatus'])->middleware('permission:levels.status');

        /*
        |--------------------------------------------------------------------------
        | MODULES
        |--------------------------------------------------------------------------
        */

        Route::get('modules', [ModuleController::class, 'index'])->middleware('permission:modules.view');

        Route::post('modules', [ModuleController::class, 'store'])->middleware('permission:modules.create');

        Route::get('modules/{id}', [ModuleController::class, 'show'])->middleware('permission:modules.view');

        Route::post('modules/{id}', [ModuleController::class, 'update'])->middleware('permission:modules.edit');

        Route::delete('modules/{id}', [ModuleController::class, 'destroy'])->middleware('permission:modules.delete');

        Route::post('modules/{id}/toggle-status', [ModuleController::class, 'toggleStatus'])->middleware('permission:modules.status');

        /*
        |--------------------------------------------------------------------------
        | CHAPTERS
        |--------------------------------------------------------------------------
        */

        Route::get('chapters', [ChapterController::class, 'index'])->middleware('permission:chapters.view');

        Route::post('chapters', [ChapterController::class, 'store'])->middleware('permission:chapters.create');

        Route::get('chapters/{id}', [ChapterController::class, 'show'])->middleware('permission:chapters.view');

        Route::post('chapters/{id}', [ChapterController::class, 'update'])->middleware('permission:chapters.edit');

        Route::delete('chapters/{id}', [ChapterController::class, 'destroy'])->middleware('permission:chapters.delete');

        Route::post('chapters/{id}/toggle-status', [ChapterController::class, 'toggleStatus'])->middleware('permission:chapters.status');

        /*
        |--------------------------------------------------------------------------
        | TOPICS
        |--------------------------------------------------------------------------
        */

        Route::get('topics', [TopicController::class, 'index'])->middleware('permission:topics.view');

        Route::post('topics', [TopicController::class, 'store'])->middleware('permission:topics.create');

        Route::get('topics/{id}', [TopicController::class, 'show'])->middleware('permission:topics.view');

        Route::post('topics/{id}', [TopicController::class, 'update'])->middleware('permission:topics.edit');

        Route::delete('topics/{id}', [TopicController::class, 'destroy'])->middleware('permission:topics.delete');

        Route::post('topics/{id}/toggle-status', [TopicController::class, 'toggleStatus'])->middleware('permission:topics.status');

        /*
        |--------------------------------------------------------------------------
        | FAQS
        |--------------------------------------------------------------------------
        */

        Route::get('faqs', [FaqController::class, 'index'])->middleware('permission:faqs.view');

        Route::post('faqs', [FaqController::class, 'store'])->middleware('permission:faqs.create');

        Route::get('faqs/{id}', [FaqController::class, 'show'])->middleware('permission:faqs.view');

        Route::post('faqs/{id}', [FaqController::class, 'update'])->middleware('permission:faqs.edit');

        Route::delete('faqs/{id}', [FaqController::class, 'destroy'])->middleware('permission:faqs.delete');

        Route::post('faqs/{id}/toggle-status', [FaqController::class, 'toggleStatus'])->middleware('permission:faqs.status');

        /*
        |--------------------------------------------------------------------------
        | MEDIA
        |--------------------------------------------------------------------------
        */

        Route::post('media', [MediaController::class, 'store'])->middleware('permission:media.create');

        Route::get('media', [MediaController::class, 'index'])->middleware('permission:media.view');

        Route::get('media/{id}', [MediaController::class, 'show'])->middleware('permission:media.view');

        Route::post('media/{id}', [MediaController::class, 'update'])->middleware('permission:media.edit');

        Route::delete('media/{id}', [MediaController::class, 'destroy'])->middleware('permission:media.delete');

        Route::post('media/{id}/toggle-status', [MediaController::class, 'toggleStatus'])->middleware('permission:media.status');

        /*
        |--------------------------------------------------------------------------
        | CONTENT
        |--------------------------------------------------------------------------
        */

        Route::post('content-topics/{topic_id}/contents', [SectionContentController::class, 'store'])->middleware('permission:content.create');

        Route::get('content-topics/{topic_id}/contents', [SectionContentController::class, 'index'])->middleware('permission:content.view');

        Route::get('content-topics/{topic_id}/full', [SectionContentController::class, 'full'])->middleware('permission:content.view');

        Route::get('contents', [SectionContentController::class, 'index'])->middleware('permission:content.view');

        Route::post('content-topics/{topic_id}/contents/reorder', [SectionContentController::class, 'reorder'])->middleware('permission:content.reorder');

        Route::post('content-topics/{topic_id}/contents/{id}/toggle-status', [SectionContentController::class, 'toggleStatus'])->middleware('permission:content.status');

        Route::get('content-topics/{topic_id}/contents/{id}', [SectionContentController::class, 'show'])->middleware('permission:content.view');

        Route::post('content-topics/{topic_id}/contents/{id}', [SectionContentController::class, 'update'])->middleware('permission:content.edit');

        Route::delete('content-topics/{topic_id}/contents/{id}', [SectionContentController::class, 'destroy'])->middleware('permission:content.delete');

        Route::post('content-topics/{topic_id}/contents/bulk', [SectionContentController::class, 'bulkStore'])->middleware('permission:content.bulk-create');

        Route::patch('content-topics/{topic_id}/contents/update-bulk', [SectionContentController::class, 'bulkUpdate'])->middleware('permission:content.bulk-edit');

        Route::get('content-topics/{topic_id}/contents/bulk-edit', [SectionContentController::class, 'bulkEdit'])->middleware('permission:content.bulk-edit');

        Route::get('content/single-preview/{topic_id}/{content_id}', [SectionContentController::class, 'single'])->middleware('permission:content.preview');

        /*
        |--------------------------------------------------------------------------
        | ASSESSMENTS
        |--------------------------------------------------------------------------
        */

        Route::prefix('assessments')->group(function () {

            Route::get('assessment-feedbacks', [FeedbackController::class, 'index'])->middleware('permission:feedbacks.view');

            Route::get('assessment-feedbacks/{id}', [FeedbackController::class, 'show'])->middleware('permission:feedbacks.view');

            Route::get('/', [AssessmentController::class, 'index'])->middleware('permission:assessments.view');

            Route::post('/', [AssessmentController::class, 'store'])->middleware('permission:assessments.create');

            Route::get('{id}', [AssessmentController::class, 'show'])->middleware('permission:assessments.view');

            Route::post('{id}', [AssessmentController::class, 'update'])->middleware('permission:assessments.edit');

            Route::delete('{id}', [AssessmentController::class, 'destroy'])->middleware('permission:assessments.delete');

            Route::post('{id}/toggle-status', [AssessmentController::class, 'toggleStatus'])->middleware('permission:assessments.status');

            Route::post('{assessment_id}/questions', [QuestionController::class, 'store'])->middleware('permission:questions.create');

            Route::get('{assessment_id}/questions', [QuestionController::class, 'index'])->middleware('permission:questions.view');

            Route::post('questions/{id}', [QuestionController::class, 'update'])->middleware('permission:questions.edit');

            Route::delete('questions/{id}', [QuestionController::class, 'destroy'])->middleware('permission:questions.delete');

            Route::get('questions/{id}', [QuestionController::class, 'show'])->middleware('permission:questions.view');

            Route::post('questions/{question_id}/options', [OptionController::class, 'store'])->middleware('permission:options.create');

            Route::post('options/{id}', [OptionController::class, 'update'])->middleware('permission:options.edit');

            Route::delete('options/{id}', [OptionController::class, 'destroy'])->middleware('permission:options.delete');

            Route::get('questions/{question_id}/options', [OptionController::class, 'index'])->middleware('permission:options.view');

            Route::get('options/{id}', [OptionController::class, 'show'])->middleware('permission:options.view');
        });

        /*
        |--------------------------------------------------------------------------
        | SETTINGS
        |--------------------------------------------------------------------------
        */

        Route::prefix('setting')->group(function () {

            Route::get('roles', [RoleController::class, 'index'])->middleware('permission:roles.view');

            Route::post('roles', [RoleController::class, 'store'])->middleware('permission:roles.create');

            Route::get('roles/{id}', [RoleController::class, 'show'])->middleware('permission:roles.view');

            Route::post('roles/{id}', [RoleController::class, 'update'])->middleware('permission:roles.edit');

            Route::delete('roles/{id}', [RoleController::class, 'destroy'])->middleware('permission:roles.delete');

            Route::post('roles/{id}/toggle-status', [RoleController::class, 'toggleStatus'])->middleware('permission:roles.status');

            Route::get('permissions', [PermissionController::class, 'index'])->middleware('permission:roles.view');

            Route::get('designations', [DesignationController::class, 'index'])->middleware('permission:designations.view');

            Route::post('designations', [DesignationController::class, 'store'])->middleware('permission:designations.create');

            Route::get('designations/{id}', [DesignationController::class, 'show'])->middleware('permission:designations.view');

            Route::post('designations/{id}', [DesignationController::class, 'update'])->middleware('permission:designations.edit');

            Route::delete('designations/{id}', [DesignationController::class, 'destroy'])->middleware('permission:designations.delete');

            Route::post('designations/{id}/toggle-status', [DesignationController::class, 'toggleStatus'])->middleware('permission:designations.status');

            Route::get('smtp', [SmtpController::class, 'get'])->middleware('permission:smtp.view');

            Route::post('smtp', [SmtpController::class, 'update'])->middleware('permission:smtp.edit');

            Route::post('smtp/test', [SmtpController::class, 'test'])->middleware('permission:smtp.test');

            Route::get('site', [SiteSettingController::class, 'get'])->middleware('permission:site-settings.view');

            Route::post('site', [SiteSettingController::class, 'update'])->middleware('permission:site-settings.edit');

            Route::post('firebase/config', [SiteSettingController::class, 'saveFirebase'])->middleware('permission:site-settings.firebase');

            Route::prefix('certificate-settings')->group(function () {

                Route::get('/', [CertificateSettingController::class, 'get'])->middleware('permission:certificate-settings.view');

                Route::post('/', [CertificateSettingController::class, 'update'])->middleware('permission:certificate-settings.edit');

                Route::get('/variables', [CertificateSettingController::class, 'variables'])->middleware('permission:certificate-settings.view');
            });

            Route::get('contacts', [ContactController::class, 'index'])->middleware('permission:contacts.view');

            Route::get('contacts/{id}', [ContactController::class, 'show'])->middleware('permission:contacts.view');

            Route::post('contacts/{id}/mark-seen', [ContactController::class, 'markSeen'])->middleware('permission:contacts.mark-seen');

            Route::post('contacts/{id}/mark-unseen', [ContactController::class, 'markUnseen'])->middleware('permission:contacts.mark-unseen');
        });

        /*
        |--------------------------------------------------------------------------
        | REPORTS
        |--------------------------------------------------------------------------
        */

        Route::prefix('reports')->group(function () {

            Route::get('/audit-logs', [AuditReportController::class, 'index'])->middleware('permission:reports.audit');

            Route::get('/user-progress', [UserProgressReportController::class, 'index'])->middleware('permission:reports.progress');

            Route::get('/assessment-report', [AssessmentReportController::class, 'index'])->middleware('permission:reports.assessment');

            Route::get('/content-status', [ContentStatusReportController::class, 'index'])->middleware('permission:reports.content-status');

            Route::get('/certifications', [CertificationReportController::class, 'index'])->middleware('permission:reports.certifications');

            Route::get('/certificate/{attempt_id}', [CertificationReportController::class, 'show'])->middleware('permission:reports.certifications');
        });
    });
});
