<?php

namespace App\Workflows;

use App\Workflows\Contracts\WorkflowDefinition;
use App\Workflows\Contracts\WorkflowStage;
use BackedEnum;
use Illuminate\Container\Attributes\Singleton;
use InvalidArgumentException;

/**
 * Maps the key stored on an instance back to its definition.
 *
 * The indirection exists so a row only ever stores a short stable key, never a class name:
 * definitions can be moved or renamed without rewriting data, and an instance whose definition
 * has been removed fails with a clear message instead of a class-not-found.
 */
#[Singleton]
class WorkflowRegistry
{
    /**
     * @var array<string, WorkflowDefinition>
     */
    private array $definitions = [];

    /**
     * @param  iterable<WorkflowDefinition>  $definitions
     */
    public function __construct(iterable $definitions = [])
    {
        foreach ($definitions as $definition) {
            $this->register($definition);
        }
    }

    public function register(WorkflowDefinition $definition): void
    {
        $this->definitions[$definition->key()] = $definition;
    }

    public function get(string $key): WorkflowDefinition
    {
        return $this->definitions[$key]
            ?? throw new InvalidArgumentException("No workflow definition registered for [{$key}].");
    }

    public function has(string $key): bool
    {
        return isset($this->definitions[$key]);
    }

    /**
     * @return array<string, WorkflowDefinition>
     */
    public function all(): array
    {
        return $this->definitions;
    }

    /**
     * Hydrate a stored stage value into its enum case.
     */
    public function stage(string $definitionKey, string $value): WorkflowStage&BackedEnum
    {
        $enum = $this->get($definitionKey)->stageEnum();
        $case = $enum::tryFrom($value);

        if ($case === null) {
            throw new InvalidArgumentException(
                "[{$value}] is not a stage of workflow [{$definitionKey}].",
            );
        }

        return $case;
    }
}
