<?php

namespace App\Support\Profile;

use App\Enums\Permission;
use App\Models\Employee;
use App\Models\ProfileChangeRequest;
use App\Models\User;

/**
 * A profile change request as the pages show it: each changed field with its label, group and the
 * before / after values (EMP-CHG-003).
 *
 * Bank and tax values are withheld from a reviewer who may not see them, in the same way the
 * profile withholds them (EMP-DET-006); the field is still listed, so they know something changed.
 */
class ProfileChangePresenter
{
    /**
     * @return array<string, mixed>
     */
    public function present(ProfileChangeRequest $request, User $viewer): array
    {
        $request->loadMissing(['employee:id,company_id,full_name,employee_number,user_id', 'requestedBy:id,name', 'decidedBy:id,name']);

        $maySeeSensitive = $viewer->can(Permission::ViewSensitiveData->value)
            || $this->isOwner($viewer, $request->employee);

        return [
            'id' => $request->id,
            'reference' => $request->reference(),
            'status' => $request->status->value,
            'statusLabel' => $request->status->label(),
            'employee' => [
                'id' => $request->employee_id,
                'name' => $request->employee?->full_name,
                'number' => $request->employee?->employee_number,
            ],
            'requestedBy' => $request->requestedBy?->name,
            'submittedAt' => $request->created_at?->toDateTimeString(),
            'decidedBy' => $request->decidedBy?->name,
            'decidedAt' => $request->decided_at?->toDateTimeString(),
            'decisionNote' => $request->decision_note,
            'conflicts' => $request->conflicts ?? [],
            'changes' => collect($request->changes)->map(function (array $change, string $field) use ($maySeeSensitive, $request): array {
                $definition = ProfileChangeRequest::FIELDS[$field] ?? ['label' => $field, 'group' => 'Lainnya', 'type' => 'text', 'sensitive' => false];
                $masked = $definition['sensitive'] && ! $maySeeSensitive;
                $isPhoto = $definition['type'] === 'photo';

                return [
                    'field' => $field,
                    'label' => $definition['label'],
                    'group' => $definition['group'],
                    'isPhoto' => $isPhoto,
                    'masked' => $masked,
                    'before' => $masked || $isPhoto ? null : $change['before'],
                    'after' => $masked || $isPhoto ? null : $change['after'],
                    'beforeUrl' => $isPhoto && $change['before'] !== null ? route('profile-changes.photo', [$request, 'before'], absolute: false) : null,
                    'afterUrl' => $isPhoto && $change['after'] !== null ? route('profile-changes.photo', [$request, 'after'], absolute: false) : null,
                ];
            })->values()->all(),
            'canDecide' => $viewer->can('decide', $request),
            'canWithdraw' => $viewer->can('withdraw', $request),
        ];
    }

    /**
     * The form's fields and the employee's current values, for the "Ajukan perubahan data" page.
     *
     * @return array{fields: list<array<string, mixed>>, values: array<string, string|null>}
     */
    public function form(Employee $employee): array
    {
        $fields = [];
        $values = [];

        foreach (ProfileChangeRequest::FIELDS as $name => $definition) {
            $fields[] = ['name' => $name, 'label' => $definition['label'], 'group' => $definition['group'], 'type' => $definition['type']];
            $values[$name] = $definition['type'] === 'photo' ? null : ($employee->getAttribute($name) === null ? null : (string) $employee->getAttribute($name));
        }

        return ['fields' => $fields, 'values' => $values];
    }

    private function isOwner(User $viewer, ?Employee $employee): bool
    {
        return $employee !== null && $employee->user_id !== null && $employee->user_id === $viewer->id;
    }
}
