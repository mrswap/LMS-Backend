<?php

use Illuminate\Support\Facades\Route;
use App\Modules\Common\Options\Controllers\OptionController;

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

});