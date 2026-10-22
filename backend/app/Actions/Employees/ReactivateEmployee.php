<?php

namespace App\Actions\Employees;

use App\Enums\EmploymentStatus;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Brings a former employee's profile back to work: a person who returns, or a profile that was closed by mistake.
 *
 * The profile becomes active and editable again and its exit date is cleared. The sign-in that offboarding switched
 * off is switched back on, roles as they were; an account still waiting for HR to activate it is left for that
 * decision. The offboarding case stays on record, and the scanner mapping it ended is not restored: HR maps the
 * person again if they are enrolled on a scanner.
 */
class ReactivateEmployee
{
    public function handle(Employee $employee, User $by): void
    {
        DB::transaction(function () use ($employee, $by): void {
            // The change is logged on the profile with who made it (EMP-PRF-003).
            $employee->update(['employment_status' => EmploymentStatus::Active, 'exited_on' => null]);

            $account = $employee->user;

            if ($account !== null && ! $account->is_active && ! $account->awaiting_activation) {
                $account->forceFill(['is_active' => true])->save();
            }

            activity('account')->performedOn($employee)->causedBy($by)->log('HR mengaktifkan kembali karyawan');
        });
    }
}
