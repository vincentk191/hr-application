<?php

use App\Http\Controllers\CompanySwitcherController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'dashboard')->name('dashboard');

    Route::put('active-company', [CompanySwitcherController::class, 'update'])
        ->name('active-company.update');
});

require __DIR__.'/settings.php';
