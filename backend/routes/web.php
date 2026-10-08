<?php

use App\Http\Controllers\AttendanceAgentController;
use App\Http\Controllers\AttendanceCalendarController;
use App\Http\Controllers\AttendanceDeviceController;
use App\Http\Controllers\AttendanceEnrolMapController;
use App\Http\Controllers\AttendanceImportController;
use App\Http\Controllers\AttendanceIssueController;
use App\Http\Controllers\AttendancePunchController;
use App\Http\Controllers\AttendanceRecapController;
use App\Http\Controllers\CalendarEventController;
use App\Http\Controllers\CompanySwitcherController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\EmployeeDocumentController;
use App\Http\Controllers\LeaveRequestController;
use App\Http\Controllers\LeaveRequestDecisionController;
use Illuminate\Support\Facades\Route;

// The app is internal and private-only (context.md §5.1), so the root is not a marketing
// page: a guest goes to sign in, a signed-in user goes to their dashboard.
Route::get('/', fn () => to_route(auth()->check() ? 'dashboard' : 'login'))->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    Route::put('active-company', [CompanySwitcherController::class, 'update'])
        ->name('active-company.update');

    Route::get('employees', [EmployeeController::class, 'index'])->name('employees.index');
    Route::get('employees/create', [EmployeeController::class, 'create'])->name('employees.create');
    Route::post('employees', [EmployeeController::class, 'store'])->name('employees.store');
    Route::get('employees/{employee}', [EmployeeController::class, 'show'])->name('employees.show');
    Route::get('employees/{employee}/edit', [EmployeeController::class, 'edit'])->name('employees.edit');
    Route::put('employees/{employee}', [EmployeeController::class, 'update'])->name('employees.update');

    Route::post('employees/{employee}/documents', [EmployeeDocumentController::class, 'store'])
        ->name('documents.store');
    Route::put('documents/{document}', [EmployeeDocumentController::class, 'update'])->name('documents.update');
    Route::delete('documents/{document}', [EmployeeDocumentController::class, 'destroy'])->name('documents.destroy');
    Route::post('documents/{document}/versions', [EmployeeDocumentController::class, 'storeVersion'])
        ->name('documents.versions.store');
    Route::get('documents/{document}/versions/{version}', [EmployeeDocumentController::class, 'download'])
        ->scopeBindings()
        ->name('documents.download');

    Route::get('attendance/calendar', [AttendanceCalendarController::class, 'index'])->name('attendance.calendar');
    Route::get('attendance/recap', [AttendanceRecapController::class, 'index'])->name('attendance.recap');
    Route::get('attendance/calendar/export', [AttendanceCalendarController::class, 'export'])->name('attendance.calendar.export');
    Route::get('attendance/issues', [AttendanceIssueController::class, 'index'])->name('attendance.issues');
    Route::post('attendance/issues/resolve', [AttendanceIssueController::class, 'resolve'])->name('attendance.issues.resolve');
    Route::post('attendance/issues/reopen', [AttendanceIssueController::class, 'reopen'])->name('attendance.issues.reopen');
    Route::post('calendar-events', [CalendarEventController::class, 'store'])->name('calendar-events.store');
    Route::delete('calendar-events/{calendarEvent}', [CalendarEventController::class, 'destroy'])->name('calendar-events.destroy');
    Route::post('attendance/agents/sync-now', [AttendanceAgentController::class, 'syncNow'])->name('attendance.agents.sync-now');
    Route::get('attendance/device', [AttendanceDeviceController::class, 'index'])->name('attendance.device');
    Route::post('attendance/imports', [AttendanceImportController::class, 'store'])->name('attendance.imports.store');
    Route::post('attendance/enrol-maps', [AttendanceEnrolMapController::class, 'store'])
        ->name('attendance.enrol-maps.store');
    Route::delete('attendance/enrol-maps/{enrolMap}', [AttendanceEnrolMapController::class, 'destroy'])
        ->name('attendance.enrol-maps.destroy');

    Route::post('employees/{employee}/punches', [AttendancePunchController::class, 'store'])->name('punches.store');
    Route::put('punches/{punch}', [AttendancePunchController::class, 'update'])->name('punches.update');
    Route::delete('punches/{punch}', [AttendancePunchController::class, 'destroy'])->name('punches.destroy');

    Route::get('leave-requests', [LeaveRequestController::class, 'index'])->name('leave-requests.index');
    Route::get('leave-requests/create', [LeaveRequestController::class, 'create'])->name('leave-requests.create');
    Route::post('leave-requests', [LeaveRequestController::class, 'store'])->name('leave-requests.store');
    Route::put('leave-requests/{leaveRequest}/decision', [LeaveRequestDecisionController::class, 'update'])
        ->name('leave-requests.decision');
});

require __DIR__.'/settings.php';
