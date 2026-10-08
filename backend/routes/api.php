<?php

use App\Http\Controllers\Api\AttendanceIngestController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API routes
|--------------------------------------------------------------------------
|
| Token-authenticated, stateless, and — by design — reachable only over the private network. The
| ingest endpoint takes pushed scanner punches from the on-site sync agent (ATT-CAL-003). Its token
| carries a single ability, so it cannot be used for anything else, and the route is throttled per
| token (CORE-AUT-007).
|
*/

Route::prefix('ingest/attendance')
    ->middleware(['auth:sanctum', 'abilities:attendance:ingest', 'throttle:attendance-ingest'])
    ->name('api.ingest.attendance.')
    ->group(function (): void {
        Route::post('/', [AttendanceIngestController::class, 'store'])->name('store');
        Route::get('instructions', [AttendanceIngestController::class, 'instructions'])->name('instructions');
    });
