<?php

use App\Http\Controllers\Api\HealthController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| This application is an API only. The user interface is a separate React SPA
| served from ../frontend, so there are no server rendered pages here — the
| web route only exists so the Laravel health check keeps working.
|
*/

Route::get('/', function () {
    return redirect()->route('api.health');
});

Route::get('/up', HealthController::class)->name('up');
