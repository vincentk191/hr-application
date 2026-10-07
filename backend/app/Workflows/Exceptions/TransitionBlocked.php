<?php

namespace App\Workflows\Exceptions;

/**
 * The move is legal but a guard refused it right now — a checklist item still outstanding, an
 * approval not yet given.
 *
 * Unlike InvalidTransition this is an ordinary thing for a user to hit, so it carries the
 * guard messages for display.
 */
class TransitionBlocked extends WorkflowException
{
    /**
     * @param  list<string>  $reasons
     */
    public function __construct(public readonly array $reasons)
    {
        parent::__construct(implode(' ', $reasons));
    }
}
