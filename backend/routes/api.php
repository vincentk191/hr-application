<?php

use App\Http\Controllers\Api\V1\ActiveCompanyController;
use App\Http\Controllers\Api\V1\ContextController;
use App\Http\Controllers\Api\V1\EmployeeController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->middleware('auth:sanctum')->group(function (): void {
    Route::get('context', ContextController::class)->name('context');
    Route::put('active-company', [ActiveCompanyController::class, 'update'])->name('active-company.update');
    Route::get('employees', [EmployeeController::class, 'index'])->name('employees.index');
    Route::get('employees/{employee}', [EmployeeController::class, 'show'])->name('employees.show');
});
