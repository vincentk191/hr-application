<?php

namespace App\Http\Controllers;

use App\Actions\Accounts\ActivateAccount;
use App\Models\Employee;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * HR's decision on an account an employee registered for themselves (CORE-ACC-006).
 */
class EmployeeAccountController extends Controller
{
    public function activate(Request $request, Employee $employee, ActivateAccount $activate): RedirectResponse
    {
        $this->authorize('activateAccount', $employee);

        $activate->activate($employee, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => "Akun {$employee->full_name} diaktifkan."]);

        return back();
    }

    public function reject(Request $request, Employee $employee, ActivateAccount $activate): RedirectResponse
    {
        $this->authorize('activateAccount', $employee);

        $activate->reject($employee, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Pendaftaran ditolak.']);

        return back();
    }
}
