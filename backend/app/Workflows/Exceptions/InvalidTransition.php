<?php

namespace App\Workflows\Exceptions;

/**
 * The move is not in the definition at all — a jump the workflow does not allow.
 *
 * This is a programming error rather than something a user did: the UI should not have offered
 * the move. It is deliberately not catchable as a validation failure.
 */
class InvalidTransition extends WorkflowException
{
    public static function between(string $definition, string $from, int|string $to): self
    {
        return new self("Workflow [{$definition}] does not allow moving from [{$from}] to [{$to}].");
    }
}
