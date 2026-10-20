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
use App\Http\Controllers\EmployeeAccountController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\EmployeeDocumentController;
use App\Http\Controllers\EmployeeImportController;
use App\Http\Controllers\EmployeePhotoController;
use App\Http\Controllers\LeaveAttachmentController;
use App\Http\Controllers\LeaveRequestController;
use App\Http\Controllers\LeaveRequestDecisionController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OffboardingCaseController;
use App\Http\Controllers\OffboardingChecklistController;
use App\Http\Controllers\OffboardingSignOffController;
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

    // Offboarding: HR makes the case, Management approves, the supervisors work the checklist (OFF-*).
    Route::get('offboarding', [OffboardingCaseController::class, 'index'])->name('offboarding.index');
    Route::get('offboarding/create', [OffboardingCaseController::class, 'create'])->name('offboarding.create');
    Route::post('offboarding', [OffboardingCaseController::class, 'store'])->name('offboarding.store');
    Route::get('offboarding/files/{file}', [OffboardingCaseController::class, 'downloadFile'])->name('offboarding.files.download');
    Route::delete('offboarding/checklist/items/{item}', [OffboardingChecklistController::class, 'remove'])->name('offboarding.checklist.remove');
    Route::put('offboarding/checklist/items/{item}/done', [OffboardingChecklistController::class, 'done'])->name('offboarding.checklist.done');
    Route::put('offboarding/checklist/items/{item}/reopen', [OffboardingChecklistController::class, 'reopen'])->name('offboarding.checklist.reopen');
    Route::post('offboarding/{offboardingCase}/checklist/{group}/items', [OffboardingChecklistController::class, 'select'])->name('offboarding.checklist.select');
    Route::post('offboarding/{offboardingCase}/checklist/{group}/adhoc', [OffboardingChecklistController::class, 'adhoc'])->name('offboarding.checklist.adhoc');
    Route::post('offboarding/{offboardingCase}/checklist/{group}/confirm', [OffboardingChecklistController::class, 'confirm'])->name('offboarding.checklist.confirm');
    Route::put('offboarding/{offboardingCase}/checklist/{group}/involvement', [OffboardingChecklistController::class, 'involvement'])->name('offboarding.checklist.involvement');
    Route::post('offboarding/{offboardingCase}/checklist/approve', [OffboardingChecklistController::class, 'approve'])->name('offboarding.checklist.approve');
    Route::post('offboarding/{offboardingCase}/checklist/refresh', [OffboardingChecklistController::class, 'refresh'])->name('offboarding.checklist.refresh');
    Route::put('offboarding/{offboardingCase}/checklist/{group}/owner', [OffboardingChecklistController::class, 'owner'])->name('offboarding.checklist.owner');
    Route::post('offboarding/{offboardingCase}/proceed', [OffboardingChecklistController::class, 'proceed'])->name('offboarding.proceed');
    Route::post('offboarding/{offboardingCase}/send-back', [OffboardingSignOffController::class, 'sendBack'])->name('offboarding.send-back');
    Route::put('offboarding/{offboardingCase}/exit-interview', [OffboardingSignOffController::class, 'interview'])->name('offboarding.interview');
    Route::put('offboarding/{offboardingCase}/last-day', [OffboardingSignOffController::class, 'lastDay'])->name('offboarding.last-day');
    Route::post('offboarding/{offboardingCase}/cancel', [OffboardingSignOffController::class, 'cancel'])->name('offboarding.cancel');
    Route::post('offboarding/{offboardingCase}/close', [OffboardingSignOffController::class, 'close'])->name('offboarding.close');
    Route::post('offboarding/{offboardingCase}/reports', [OffboardingSignOffController::class, 'reassign'])->name('offboarding.reports');
    Route::get('offboarding/{offboardingCase}/berita-acara', [OffboardingSignOffController::class, 'beritaAcara'])->name('offboarding.berita-acara');
    Route::get('offboarding/{offboardingCase}', [OffboardingCaseController::class, 'show'])->name('offboarding.show');
    Route::get('offboarding/{offboardingCase}/edit', [OffboardingCaseController::class, 'edit'])->name('offboarding.edit');
    Route::put('offboarding/{offboardingCase}', [OffboardingCaseController::class, 'update'])->name('offboarding.update');
    Route::delete('offboarding/{offboardingCase}', [OffboardingCaseController::class, 'destroy'])->name('offboarding.destroy');
    Route::post('offboarding/{offboardingCase}/submit', [OffboardingCaseController::class, 'submit'])->name('offboarding.submit');
    Route::put('offboarding/{offboardingCase}/decision', [OffboardingCaseController::class, 'decide'])->name('offboarding.decide');
    Route::post('offboarding/{offboardingCase}/comments', [OffboardingCaseController::class, 'comment'])->name('offboarding.comments.store');
    Route::post('offboarding/{offboardingCase}/comments/read', [OffboardingCaseController::class, 'markCommentsRead'])->name('offboarding.comments.read');
    Route::post('offboarding/{offboardingCase}/files', [OffboardingCaseController::class, 'addFiles'])->name('offboarding.files.store');

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
    Route::post('employees/{employee}/activation', [EmployeeAccountController::class, 'activate'])->name('employees.activation.activate');
    Route::delete('employees/{employee}/activation', [EmployeeAccountController::class, 'reject'])->name('employees.activation.reject');
    Route::get('employees/{employee}', [EmployeeController::class, 'show'])->name('employees.show');
    Route::get('employees/{employee}/photo', [EmployeePhotoController::class, 'show'])->name('employees.photo');
    Route::get('profile-changes/{profileChange}/photo/{side}', [EmployeePhotoController::class, 'change'])->name('profile-changes.photo');
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
