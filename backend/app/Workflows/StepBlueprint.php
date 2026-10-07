<?php

namespace App\Workflows;

use App\Enums\Role;
use App\Models\User;

/**
 * One step of a chain, as described before it exists in the database.
 *
 * A module building a chain says what each step requires — a role, a named person, or both —
 * and the engine turns these into rows. Passing a value object rather than an array means a
 * malformed chain is a type error at the call site instead of a surprise three stages later.
 */
final readonly class StepBlueprint
{
    public function __construct(
        public string $label,
        public ?string $approverRole = null,
        public ?int $approverUserId = null,
    ) {
        if ($this->approverRole === null && $this->approverUserId === null) {
            throw new \InvalidArgumentException(
                "Step [{$this->label}] needs an approver role, an approver user, or both.",
            );
        }
    }

    /**
     * A step anyone holding the role may decide.
     */
    public static function forRole(string $label, Role $role): self
    {
        return new self($label, approverRole: $role->value);
    }

    /**
     * A step only this person may decide — the direct supervisor from the dossier, an item's
     * owner (CORE-RBA-005, OFF-WZ1-002).
     */
    public static function forUser(string $label, User|int $user): self
    {
        return new self($label, approverUserId: $user instanceof User ? $user->id : $user);
    }

    /**
     * A step that requires the role, already resolved to the person who holds it here.
     */
    public static function forUserAsRole(string $label, User|int $user, Role $role): self
    {
        return new self(
            $label,
            approverRole: $role->value,
            approverUserId: $user instanceof User ? $user->id : $user,
        );
    }
}
