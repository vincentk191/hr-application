<?php

namespace App\Actions\Profile;

use App\Enums\ProfileChangeStatus;
use App\Models\Employee;
use App\Models\ProfileChangeRequest;
use App\Models\User;
use App\Support\Notifications\Notifier;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * An employee asks to change their own profile (EMP-CHG-001, EMP-CHG-002).
 *
 * Only the fields that actually differ are kept, each with the value the employee saw when they
 * asked (the "before"), which is what lets an approval notice that HR changed the field in between.
 * Nothing on the profile changes here.
 */
class SubmitProfileChange
{
    /**
     * @param  array<string, mixed>  $values  the form as sent: field name to new value
     *
     * @throws ValidationException
     */
    public function handle(Employee $employee, array $values, ?UploadedFile $photo, User $by): ProfileChangeRequest
    {
        if ($this->openRequestFor($employee) !== null) {
            throw ValidationException::withMessages(['form' => 'Anda masih punya permintaan yang menunggu HR. Tarik atau tunggu keputusannya dulu.']);
        }

        $changes = $this->differences($employee, $values);
        $storedPhoto = null;

        if ($photo !== null) {
            $storedPhoto = $photo->store('employee-photos', 'public');
            $changes['photo_path'] = ['before' => $employee->photo_path, 'after' => $storedPhoto];
        }

        if ($changes === []) {
            throw ValidationException::withMessages(['form' => 'Tidak ada perubahan untuk diajukan.']);
        }

        $request = DB::transaction(fn (): ProfileChangeRequest => ProfileChangeRequest::create([
            'company_id' => $employee->company_id,
            'employee_id' => $employee->id,
            'requested_by_user_id' => $by->id,
            'status' => ProfileChangeStatus::Pending,
            'changes' => $changes,
        ]));

        app(Notifier::class)->profileChangeSubmitted($request, $by);

        return $request;
    }

    /**
     * The request still waiting for HR, if any: an employee has one at a time.
     */
    public function openRequestFor(Employee $employee): ?ProfileChangeRequest
    {
        return ProfileChangeRequest::query()->pending()->where('employee_id', $employee->id)->first();
    }

    /**
     * What the form changes, as {field: {before, after}}. A field missing from the form is left
     * alone; an emptied one means "clear it".
     *
     * @param  array<string, mixed>  $values
     * @return array<string, array{before: string|null, after: string|null}>
     */
    private function differences(Employee $employee, array $values): array
    {
        $changes = [];

        foreach (ProfileChangeRequest::FIELDS as $field => $definition) {
            if ($definition['type'] === 'photo' || ! array_key_exists($field, $values)) {
                continue;
            }

            $after = trim((string) $values[$field]);
            $after = $after === '' ? null : $after;
            $before = $employee->getAttribute($field);
            $before = $before === null || $before === '' ? null : (string) $before;

            if ($after !== $before) {
                $changes[$field] = ['before' => $before, 'after' => $after];
            }
        }

        return $changes;
    }

    /**
     * Take back a request that is still waiting. The picture uploaded with it is deleted.
     */
    public function withdraw(ProfileChangeRequest $request): void
    {
        $request->update(['status' => ProfileChangeStatus::Withdrawn]);

        $pending = $request->changes['photo_path']['after'] ?? null;

        if ($pending !== null) {
            Storage::disk('public')->delete($pending);
        }
    }
}
