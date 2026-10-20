<?php

namespace App\Actions\Accounts;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * HR activates an account an employee registered for themselves (CORE-ACC-006), or rejects the claim.
 */
class ActivateAccount
{
    /**
     * @throws ValidationException
     */
    public function activate(Employee $employee, User $by): void
    {
        $account = $this->pending($employee);

        $account->forceFill(['awaiting_activation' => false, 'is_active' => true])->save();

        activity('account')->performedOn($employee)->causedBy($by)->log('HR mengaktifkan akun yang didaftarkan karyawan');
    }

    /**
     * Someone else claimed this profile. The account is removed and the profile is free to be claimed by the
     * right person; it never signed in, so there is no history to keep.
     *
     * @throws ValidationException
     */
    public function reject(Employee $employee, User $by): void
    {
        $account = $this->pending($employee);

        $employee->forceFill(['user_id' => null])->save();
        $account->delete();

        activity('account')->performedOn($employee)->causedBy($by)->log('HR menolak pendaftaran akun');
    }

    /**
     * @throws ValidationException
     */
    private function pending(Employee $employee): User
    {
        $account = $employee->user_id === null ? null : User::query()->find($employee->user_id);

        if ($account === null || ! $account->awaiting_activation) {
            throw ValidationException::withMessages(['account' => 'Tidak ada pendaftaran yang menunggu aktivasi.']);
        }

        return $account;
    }
}
