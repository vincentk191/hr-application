<?php

namespace App\Workflows\Contracts;

use BackedEnum;

/**
 * The rules of one workflow: its stages, which moves are legal, and what blocks them
 * (CORE-WFL-001/004).
 *
 * A definition is pure declaration with no state of its own, so it can be resolved from the
 * container and asked about an instance without touching the database.
 */
interface WorkflowDefinition
{
    /**
     * Stable key stored on the instance. Changing it orphans existing rows.
     */
    public function key(): string;

    /**
     * The stage a new instance starts in.
     */
    public function initialStage(): WorkflowStage&BackedEnum;

    /**
     * The stage enum class this definition works in.
     *
     * @return class-string<WorkflowStage&BackedEnum>
     */
    public function stageEnum(): string;

    /**
     * Which stages may follow a given stage.
     *
     * Movement is forward-only by default; a definition that allows a return (a rejection
     * sending a case back to draft) declares it here explicitly.
     *
     * @return array<string, list<string>> keyed by stage value
     */
    public function transitions(): array;

    /**
     * Guards that must pass before entering a stage (CORE-WFL-004).
     *
     * @return array<string, list<class-string<TransitionGuard>>> keyed by target stage value
     */
    public function guards(): array;
}
