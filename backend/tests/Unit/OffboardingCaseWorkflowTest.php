<?php

use App\Enums\OffboardingCaseStage as Stage;
use App\Workflows\Definitions\OffboardingCaseWorkflow;
use App\Workflows\Guards\ChainFullyApproved;
use App\Workflows\Guards\ChecklistComplete;
use App\Workflows\Guards\LastDayPassed;
use App\Workflows\Guards\ReportsReassigned;
use App\Workflows\Guards\SignOffsComplete;

/**
 * The offboarding pipeline's shape (context.md §9.4, OFF-PIP-002).
 *
 * A definition is pure declaration, so this needs no database — which is the point of keeping
 * definitions free of state.
 */
beforeEach(function () {
    $this->definition = new OffboardingCaseWorkflow;
});

it('declares every stage of the pipeline', function () {
    expect($this->definition->stageEnum())->toBe(Stage::class)
        ->and(array_keys($this->definition->transitions()))->toBe([
            Stage::Draft->value,
            Stage::Submitted->value,
            Stage::Checklist->value,
            Stage::SignOff->value,
            Stage::Closed->value,
            Stage::Cancelled->value,
        ]);
});

it('starts in draft and ends closed or cancelled', function () {
    expect($this->definition->initialStage())->toBe(Stage::Draft)
        ->and(Stage::Closed->isFinal())->toBeTrue()
        ->and(Stage::Cancelled->isFinal())->toBeTrue()
        ->and(Stage::SignOff->isFinal())->toBeFalse()
        ->and($this->definition->transitions()[Stage::Closed->value])->toBe([])
        ->and($this->definition->transitions()[Stage::Cancelled->value])->toBe([]);
});

it('moves forward one stage at a time', function () {
    $transitions = $this->definition->transitions();

    expect($transitions[Stage::Draft->value])->toContain(Stage::Submitted->value)
        ->and($transitions[Stage::Checklist->value])->toBe([Stage::SignOff->value, Stage::Cancelled->value])
        // A signer who finds something missing can send the case back; a case can be cancelled instead of closed.
        ->and($transitions[Stage::SignOff->value])->toBe([Stage::Closed->value, Stage::Checklist->value, Stage::Cancelled->value])
        // No skipping the checklist to get to sign-off.
        ->and($transitions[Stage::Submitted->value])->not->toContain(Stage::SignOff->value);
});

it('allows a rejection to send the case back to draft', function () {
    // OFF-APR-006: a rejection returns the request to its requester to correct.
    expect($this->definition->transitions()[Stage::Submitted->value])
        ->toContain(Stage::Draft->value);
});

it('guards the two stages that need approval and a finished checklist', function () {
    $guards = $this->definition->guards();

    expect($guards[Stage::Checklist->value])->toBe([ChainFullyApproved::class])
        // Sign-off waits for Management's approval and for every checklist item to be done (OFF-CHK-001).
        ->and($guards[Stage::SignOff->value])->toBe([ChainFullyApproved::class, ChecklistComplete::class])
        // Submitting a draft is the requester's own action, so it is not gated.
        // Closing needs every signature, the last day behind us and the reports moved on; cancelling is not gated.
        ->and($guards[Stage::Closed->value])->toBe([SignOffsComplete::class, LastDayPassed::class, ReportsReassigned::class])
        ->and($guards)->not->toHaveKey(Stage::Cancelled->value)
        ->and($guards)->not->toHaveKey(Stage::Submitted->value);
});

it('labels every stage in Indonesian', function () {
    foreach (Stage::cases() as $stage) {
        expect($stage->label())->not->toBeEmpty();
    }

    expect(Stage::Submitted->label())->toBe('Diajukan')
        ->and(Stage::SignOff->label())->toBe('Sign-off');
});

it('has a stable key', function () {
    // Stored on every instance row; changing it orphans existing data.
    expect($this->definition->key())->toBe('offboarding_case');
});
