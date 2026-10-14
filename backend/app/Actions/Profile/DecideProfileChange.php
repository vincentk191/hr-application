<?php

namespace App\Actions\Profile;

use App\Enums\ProfileChangeStatus;
use App\Models\Employee;
use App\Models\ProfileChangeRequest;
use App\Models\User;
use App\Support\Notifications\Notifier;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * HR's decision on an employee's profile change request (EMP-CHG-003, EMP-CHG-004).
 *
 * Approving applies each field whose current value still matches the "before" the employee saw. A
 * field HR changed in the meantime is left as HR set it and reported back as a conflict, so an
 * approval can never silently overwrite a newer value.
 */
class DecideProfileChange
{
    /**
     * @return list<string> the labels of the fields that were not applied because they changed meanwhile
     */
    public function approve(ProfileChangeRequest $request, User $by, ?string $note = null): array
    {
        $conflicts = DB::transaction(function () use ($request, $by, $note): array {
            $locked = ProfileChangeRequest::query()->whereKey($request->id)->lockForUpdate()->firstOrFail();

            if (! $locked->isPending()) {
                return $locked->conflicts ?? [];
            }

            $employee = Employee::acrossCompanies()->whereKey($locked->employee_id)->firstOrFail();
            $apply = [];
            $skipped = [];

            foreach ($locked->changes as $field => $change) {
                $current = $employee->getAttribute($field);
                $current = $current === null || $current === '' ? null : (string) $current;

                if ($current === $change['before']) {
                    $apply[$field] = $change['after'];
                } else {
                    $skipped[] = $field;
                }
            }

            // The employee's own update is the audited change (EMP-PRF-003); who asked and who
            // approved are added below so the trail reads as both.
            if ($apply !== []) {
                $previousPhoto = isset($apply['photo_path']) ? $employee->photo_path : null;
                $employee->forceFill($apply)->save();

                if ($previousPhoto !== null) {
                    Storage::disk('public')->delete($previousPhoto);
                }
            }

            foreach ($skipped as $field) {
                $pending = $field === 'photo_path' ? ($locked->changes['photo_path']['after'] ?? null) : null;

                if ($pending !== null) {
                    Storage::disk('public')->delete($pending);
                }
            }

            $labels = array_map(fn (string $field): string => ProfileChangeRequest::FIELDS[$field]['label'] ?? $field, $skipped);

            $locked->update([
                'status' => ProfileChangeStatus::Approved,
                'decided_by_user_id' => $by->id,
                'decided_at' => now(),
                'decision_note' => $note,
                'conflicts' => $labels === [] ? null : $labels,
            ]);

            activity('employee')
                ->performedOn($employee)
                ->causedBy($by)
                ->withProperties([
                    'request' => $locked->reference(),
                    'requested_by' => $locked->requestedBy?->name,
                    'applied' => array_keys($apply),
                    'not_applied' => $skipped,
                ])
                ->log('Perubahan data dari karyawan disetujui');

            return $labels;
        });

        app(Notifier::class)->profileChangeDecided($request->refresh(), true, $by);

        return $conflicts;
    }

    public function reject(ProfileChangeRequest $request, User $by, string $note): void
    {
        DB::transaction(function () use ($request, $by, $note): void {
            $locked = ProfileChangeRequest::query()->whereKey($request->id)->lockForUpdate()->firstOrFail();

            if (! $locked->isPending()) {
                return;
            }

            $locked->update([
                'status' => ProfileChangeStatus::Rejected,
                'decided_by_user_id' => $by->id,
                'decided_at' => now(),
                'decision_note' => $note,
            ]);

            $pending = $locked->changes['photo_path']['after'] ?? null;

            if ($pending !== null) {
                Storage::disk('public')->delete($pending);
            }
        });

        app(Notifier::class)->profileChangeDecided($request->refresh(), false, $by);
    }
}
