<?php

namespace App\Actions\Accounts;

use App\Enums\EmploymentStatus;
use App\Enums\Role;
use App\Models\Employee;
use App\Models\User;
use App\Support\Notifications\Notifier;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Turns an employee's profile into a working account when the employee claims it (CORE-REG-002).
 *
 * HR creates the profile; the employee proves they are the person with the NIK and employee ID on it
 * and chooses a password. NIK and ID are not secrets, and the app has a public entrance, so the account
 * is created **waiting for HR to activate it** (CORE-ACC-006): it cannot sign in until HR has looked at
 * it, and HR can reject a claim by someone else.
 *
 * Every way of not matching (no such pair, already registered, former employee) ends in the same
 * message, so the form cannot be used to find out which NIKs exist.
 */
class RegisterEmployeeAccount
{
    public const REFUSAL = 'Data tidak cocok atau akun sudah terdaftar. Hubungi HR.';

    /**
     * @param  array<string, mixed>  $data  national_id, employee_number, password and an optional email
     *
     * @throws ValidationException
     */
    public function handle(array $data, ?string $ip = null): User
    {
        $user = DB::transaction(function () use ($data, $ip): array {
            $employee = Employee::acrossCompanies()
                ->where('national_id', trim((string) $data['national_id']))
                ->whereRaw('lower(employee_number) = ?', [Str::lower(trim((string) $data['employee_number']))])
                ->whereNull('user_id')
                ->where('employment_status', EmploymentStatus::Active)
                ->lockForUpdate()
                ->first();

            if ($employee === null) {
                throw ValidationException::withMessages(['registration' => self::REFUSAL]);
            }

            $user = new User([
                'name' => $employee->full_name,
                'email' => filled($data['email'] ?? null) ? Str::lower(trim((string) $data['email'])) : null,
                'password' => (string) $data['password'],
                'company_id' => $employee->company_id,
            ]);
            // Not mass-assignable on purpose. Never asked to verify an email (decided 2026-10-14).
            $user->forceFill(['email_verified_at' => now(), 'is_active' => false, 'awaiting_activation' => true, 'must_change_password' => false])->save();
            $user->assignRole(Role::Employee->value);

            // The claim is atomic: if another request got there first, nothing is kept.
            $claimed = Employee::acrossCompanies()->whereKey($employee->id)->whereNull('user_id')->update(['user_id' => $user->id]);

            if ($claimed !== 1) {
                throw ValidationException::withMessages(['registration' => self::REFUSAL]);
            }

            activity('account')
                ->performedOn($employee)
                ->causedBy($user)
                ->withProperties(['ip' => $ip, 'employee_number' => $employee->employee_number])
                ->log('Karyawan mendaftarkan akunnya sendiri');

            return [$user, $employee];
        });

        [$user, $employee] = $user;

        app(Notifier::class)->accountRegistered($employee, $user);

        return $user;
    }
}
