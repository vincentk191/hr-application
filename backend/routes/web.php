<?php

use App\Http\Controllers\AccountRegistrationController;
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
use App\Http\Controllers\EmployeeImportController;
use App\Http\Controllers\LeaveAttachmentController;
use App\Http\Controllers\LeaveRequestController;
use App\Http\Controllers\LeaveRequestDecisionController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProfileChangeRequestController;
use App\Http\Controllers\SearchController;
use Illuminate\Support\Facades\Route;

// The app is internal and private-only (context.md §5.1), so the root is not a marketing
// page: a guest goes to sign in, a signed-in user goes to their dashboard.
Route::get('/', fn () => to_route(auth()->check() ? 'dashboard' : 'login'))->name('home');

// Claiming the account for a profile HR has created (CORE-REG). Guests only.
Route::middleware('guest')->group(function () {
    Route::get('register', [AccountRegistrationController::class, 'create'])->name('register');
    Route::post('register', [AccountRegistrationController::class, 'store'])
        ->middleware('throttle:register')
        ->name('register.store');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    Route::put('active-company', [CompanySwitcherController::class, 'update'])
        ->name('active-company.update');

    // Changes an employee asks to make to their own profile, and HR's review of them (EMP-CHG).
    Route::get('employees/{employee}/changes', [ProfileChangeRequestController::class, 'create'])->name('employees.changes.create');
    Route::post('employees/{employee}/changes', [ProfileChangeRequestController::class, 'store'])->name('employees.changes.store');
    Route::get('profile-changes', [ProfileChangeRequestController::class, 'index'])->name('profile-changes.index');
    Route::put('profile-changes/{profileChange}/decision', [ProfileChangeRequestController::class, 'decide'])->name('profile-changes.decide');
    Route::delete('profile-changes/{profileChange}', [ProfileChangeRequestController::class, 'destroy'])->name('profile-changes.destroy');

    Route::get('employees', [EmployeeController::class, 'index'])->name('employees.index');
    Route::get('employees/create', [EmployeeController::class, 'create'])->name('employees.create');
    Route::get('employees/import', [EmployeeImportController::class, 'create'])->name('employees.import');
    Route::post('employees/import', [EmployeeImportController::class, 'store'])->name('employees.import.store');
    Route::get('employees/import/template', [EmployeeImportController::class, 'template'])->name('employees.import.template');
    Route::get('employees/export', [EmployeeController::class, 'export'])->name('employees.export');
    Route::post('employees', [EmployeeController::class, 'store'])->name('employees.store');
    Route::get('employees/{employee}', [EmployeeController::class, 'show'])->name('employees.show');
    Route::get('employees/{employee}/summary', [EmployeeController::class, 'summary'])->name('employees.summary');
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

    Route::get('search', [SearchController::class, 'index'])->name('search');
    Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');
    Route::get('notifications/{notification}', [NotificationController::class, 'open'])->name('notifications.open');

    Route::get('leave-requests', [LeaveRequestController::class, 'index'])->name('leave-requests.index');
    Route::get('leave-requests/export', [LeaveRequestController::class, 'export'])->name('leave-requests.export');
    Route::get('leave-requests/create', [LeaveRequestController::class, 'create'])->name('leave-requests.create');
    Route::post('leave-requests', [LeaveRequestController::class, 'store'])->name('leave-requests.store');
    Route::get('leave-requests/{leaveRequest}/edit', [LeaveRequestController::class, 'edit'])->name('leave-requests.edit');
    Route::put('leave-requests/{leaveRequest}', [LeaveRequestController::class, 'update'])->name('leave-requests.update');
    Route::get('leave-attachments/{attachment}', [LeaveAttachmentController::class, 'download'])->name('leave-attachments.download');
    Route::put('leave-requests/{leaveRequest}/decision', [LeaveRequestDecisionController::class, 'update'])
        ->name('leave-requests.decision');
});

require __DIR__.'/settings.php';
