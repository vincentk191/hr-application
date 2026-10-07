<?php

namespace App\Listeners;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Spatie\Permission\Contracts\Role as RoleContract;
use Spatie\Permission\Events\RoleAttachedEvent;
use Spatie\Permission\Events\RoleDetachedEvent;
use Spatie\Permission\Models\Role;

/**
 * Records every role assignment and removal (CORE-RBA-007).
 *
 * Role changes are the one edit that can widen someone's access, so they are worth being able
 * to account for afterwards. Written to the same activity log as dossier edits, against the
 * user whose roles changed, so "what happened to this account" is a single query.
 */
class LogRoleChange
{
    public function handleAttached(RoleAttachedEvent $event): void
    {
        $this->log($event->model, $event->rolesOrIds, 'granted');
    }

    public function handleDetached(RoleDetachedEvent $event): void
    {
        $this->log($event->model, $event->rolesOrIds, 'revoked');
    }

    /**
     * Spatie passes whatever the caller gave it — ids, names, Role instances or a Collection —
     * so this normalises to role names before anything is written.
     */
    private function log(Model $subject, mixed $rolesOrIds, string $action): void
    {
        $names = $this->roleNames($rolesOrIds);

        if ($names === []) {
            return;
        }

        activity('roles')
            ->performedOn($subject)
            ->withProperties(['roles' => $names, 'action' => $action])
            ->log("Role {$action}: ".implode(', ', $names));
    }

    /**
     * @return list<string>
     */
    private function roleNames(mixed $rolesOrIds): array
    {
        $items = match (true) {
            $rolesOrIds instanceof Collection => $rolesOrIds->all(),
            is_array($rolesOrIds) => $rolesOrIds,
            default => [$rolesOrIds],
        };

        $ids = [];
        $names = [];

        foreach ($items as $item) {
            if ($item instanceof RoleContract) {
                $names[] = $item->name;
            } elseif (is_int($item) || ctype_digit((string) $item)) {
                $ids[] = $item;
            } elseif (is_string($item)) {
                $names[] = $item;
            }
        }

        if ($ids !== []) {
            $names = [...$names, ...Role::whereKey($ids)->pluck('name')->all()];
        }

        return array_values(array_unique($names));
    }
}
