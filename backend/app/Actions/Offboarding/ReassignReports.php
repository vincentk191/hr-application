<?php

namespace App\Actions\Offboarding;

use App\Enums\EmploymentStatus;
use App\Models\Employee;
use App\Models\OffboardingCase;
use App\Models\User;
use App\Support\ReportingLine;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * HR gives the leaver's direct reports a new Atasan (the guard on closing needs none left). Each move is
 * checked: the person must report to the leaver now, the new Atasan must be an active person other than
 * the leaver, and no one can be put under someone who works for them.
 */
class ReassignReports
{
    /**
     * @param  array<int|string, int|string>  $moves  employee id => new manager id
     *
     * @throws ValidationException
     */
    public function handle(OffboardingCase $case, array $moves, User $by): int
    {
        return DB::transaction(function () use ($case, $moves): int {
            $moved = 0;

            foreach ($moves as $employeeId => $managerId) {
                $employee = Employee::query()->whereKey((int) $employeeId)->where('manager_id', $case->employee_id)->first();
                $manager = Employee::query()->whereKey((int) $managerId)->where('employment_status', EmploymentStatus::Active)->first();

                if ($employee === null) {
                    throw ValidationException::withMessages(['moves' => 'Karyawan ini bukan bawahan langsung lagi.']);
                }

                if ($manager === null || $manager->id === $case->employee_id) {
                    throw ValidationException::withMessages(['moves' => "Atasan baru untuk {$employee->full_name} tidak valid."]);
                }

                if ($manager->id === $employee->id || in_array($manager->id, app(ReportingLine::class)->downlineIds($employee), strict: true)) {
                    throw ValidationException::withMessages(['moves' => "{$manager->full_name} bekerja di bawah {$employee->full_name}, jadi tidak bisa menjadi atasannya."]);
                }

                $employee->update(['manager_id' => $manager->id]);
                $moved++;
            }

            return $moved;
        });
    }
}
