<?php

use Illuminate\Support\Facades\Route;
use App\Modules\Common\Options\Controllers\OptionController;
use App\Modules\Common\Settings\Controllers\SiteSettingController;



Route::prefix('v1/common')->group(function () {

    /*
    |--------------------------------------------------
    | 🌐 OPTIONS (PUBLIC)
    |--------------------------------------------------
    */

    // Roles (status: 0,1,all)
    Route::get('/roles', [OptionController::class, 'roles']);

    // Designations (status: 0,1,all)
    Route::get('/designations', [OptionController::class, 'designations']);
    Route::get('/site/settings', [SiteSettingController::class, 'get']);
});