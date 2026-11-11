<?php

namespace App\Actions\Recruitment;

use App\Models\Employee;
use App\Models\Requisition;
use App\Models\User;
use App\Support\Notifications\Notifier;

/**
 * HR names a different direct superior (Atasan langsung) for a requisition already under way: the person first named has
 * left, was the wrong one, or has no account to choose with. Choices the old superior already made stay, and the new
 * one may keep or take them back. Both are told, and the change is on the audit trail.
 */
class ChangeDirectSuperior
{
    public function __construct(private Notifier $notifier) {}

    public function handle(Requisition $requisition, Employee $superior, User $by, ?string $note = null): void
    {
        $previous = Employee::acrossCompanies()->with('user')->find($requisition->manager_employee_id);

        $requisition->forceFill(['manager_employee_id' => $superior->id])->save();

        activity('recruitment')
            ->performedOn($requisition)
            ->causedBy($by)
            ->withProperties(array_filter([
                'from' => $previous?->full_name,
                'to' => $superior->full_name,
                'note' => filled($note) ? trim((string) $note) : null,
            ]))
            ->log('Atasan langsung diganti');

        $this->notifier->directSuperiorChanged($requisition, $previous, $superior, $by);
    }
}
