<?php

use App\Http\Controllers\CompanySwitcherController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\LeaveRequestController;
use App\Http\Controllers\LeaveRequestDecisionController;
use Illuminate\Support\Facades\Route;

// The app is internal and private-only (context.md §5.1), so the root is not a marketing
// page: a guest goes to sign in, a signed-in user goes to their dashboard.
Route::get('/', fn () => to_route(auth()->check() ? 'dashboard' : 'login'))->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'dashboard')->name('dashboard');

    Route::put('active-company', [CompanySwitcherController::class, 'update'])
        ->name('active-company.update');

    Route::get('employees', [EmployeeController::class, 'index'])->name('employees.index');
    Route::get('employees/create', [EmployeeController::class, 'create'])->name('employees.create');
    Route::post('employees', [EmployeeController::class, 'store'])->name('employees.store');
    Route::get('employees/{employee}', [EmployeeController::class, 'show'])->name('employees.show');
    Route::get('employees/{employee}/edit', [EmployeeController::class, 'edit'])->name('employees.edit');
    Route::put('employees/{employee}', [EmployeeController::class, 'update'])->name('employees.update');

    Route::get('leave-requests', [LeaveRequestController::class, 'index'])->name('leave-requests.index');
    Route::get('leave-requests/create', [LeaveRequestController::class, 'create'])->name('leave-requests.create');
    Route::post('leave-requests', [LeaveRequestController::class, 'store'])->name('leave-requests.store');
    Route::put('leave-requests/{leaveRequest}/decision', [LeaveRequestDecisionController::class, 'update'])
        ->name('leave-requests.decision');
});

require __DIR__.'/settings.php';
