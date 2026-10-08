<?php

namespace App\Workflows;

use App\Enums\WorkflowStepStatus;
use App\Models\User;
use App\Models\WorkflowComment;
use App\Models\WorkflowInstance;
use App\Models\WorkflowStep;
use App\Workflows\Contracts\TransitionGuard;
use App\Workflows\Contracts\WorkflowDefinition;
use App\Workflows\Contracts\WorkflowStage;
use App\Workflows\Exceptions\InvalidTransition;
use App\Workflows\Exceptions\StepNotActionable;
use App\Workflows\Exceptions\TransitionBlocked;
use BackedEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * The shared approval engine behind Requests, Recruitment and Offboarding (CORE-WFL).
 *
 * Every state change in a workflow goes through this class. That is the point: the rules about
 * what may move, who may decide, and what gets recorded live in one place, so three modules
 * cannot drift into three slightly different notions of "approved".
 *
 * Each public method is a single transaction, because a half-applied transition — a stage
 * moved but its chain not re-locked — would leave a case that nobody can act on.
 */
class WorkflowEngine
{
    public function __construct(private WorkflowRegistry $registry) {}

    /**
     * Put a subject into a workflow and build its approval chain.
     *
     * @param  list<StepBlueprint>  $steps
     */
    public function start(
        Model $subject,
        WorkflowDefinition $definition,
        array $steps = [],
        ?User $actor = null,
    ): WorkflowInstance {
        return DB::transaction(function () use ($subject, $definition, $steps, $actor): WorkflowInstance {
            $stage = $definition->initialStage();

            $instance = new WorkflowInstance([
                'definition' => $definition->key(),
                'current_stage' => $stage->value,
                'stage_entered_at' => now(),
            ]);

            // The workflow is tagged to the subject's company, so an instance can never be
            // scoped differently from the thing it tracks.
            $instance->company_id = $subject->getAttribute('company_id');
            $instance->subject()->associate($subject);
            $instance->save();

            $this->buildChain($instance, $steps);

            $instance->transitions()->create([
                'from_stage' => null,
                'to_stage' => $stage->value,
                'actor_user_id' => $actor?->id,
            ]);

            return $instance->refresh();
        });
    }

    /**
     * Replace an instance's approval chain.
     *
     * Used when a stage decides its own approvers — offboarding builds the checklist owners'
     * steps only once HR has assembled the checklist (OFF-CHK-002/009).
     *
     * @param  list<StepBlueprint>  $steps
     */
    public function setChain(WorkflowInstance $instance, array $steps): WorkflowInstance
    {
        return DB::transaction(function () use ($instance, $steps): WorkflowInstance {
            $instance->steps()->delete();
            $this->buildChain($instance, $steps);

            return $instance->refresh();
        });
    }

    /**
     * Send a settled instance round again: keep what the approvers said, build a fresh chain, and
     * move back to the given stage.
     *
     * The notes left on the old steps are copied into the comment thread first, written by the
     * person who left them and dated when they decided, because rebuilding the chain discards the
     * steps they were stored on. Nothing an approver wrote is lost by a resubmission.
     *
     * @param  list<StepBlueprint>  $steps
     */
    public function reopen(WorkflowInstance $instance, array $steps, WorkflowStage&BackedEnum $stage, User $actor): WorkflowInstance
    {
        return DB::transaction(function () use ($instance, $steps, $stage, $actor): WorkflowInstance {
            foreach ($instance->steps()->get() as $step) {
                if (! $step->status->isDecided() || trim((string) $step->note) === '') {
                    continue;
                }

                $comment = new WorkflowComment([
                    'user_id' => $step->decided_by_user_id ?? $actor->id,
                    'stage' => $step->status->value,
                    'body' => (string) $step->note,
                ]);
                $decidedAt = $step->decided_at ?? now();
                $comment->created_at = Carbon::instance($decidedAt);
                $comment->updated_at = Carbon::instance($decidedAt);
                $instance->comments()->save($comment);
            }

            $this->setChain($instance, $steps);

            return $this->transitionTo($instance->refresh(), $stage, $actor);
        });
    }

    /**
     * Move to a new stage (CORE-WFL-001/004).
     *
     * @throws InvalidTransition the definition does not allow this move at all
     * @throws TransitionBlocked the move is allowed but a guard refuses it right now
     */
    public function transitionTo(
        WorkflowInstance $instance,
        WorkflowStage&BackedEnum $stage,
        ?User $actor = null,
    ): WorkflowInstance {
        $definition = $this->definitionFor($instance);
        $from = $instance->current_stage;

        if (! $this->canMoveTo($instance, $stage)) {
            throw InvalidTransition::between($definition->key(), $from, $stage->value);
        }

        $reasons = $this->blockingReasons($instance, $stage);

        if ($reasons !== []) {
            throw new TransitionBlocked($reasons);
        }

        return DB::transaction(function () use ($instance, $stage, $actor, $from): WorkflowInstance {
            $instance->update([
                'current_stage' => $stage->value,
                'stage_entered_at' => now(),
                'completed_at' => $stage->isFinal() ? now() : null,
            ]);

            $instance->transitions()->create([
                'from_stage' => $from,
                'to_stage' => $stage->value,
                'actor_user_id' => $actor?->id,
            ]);

            return $instance->refresh();
        });
    }

    /**
     * Whether the definition permits moving to this stage from where the instance is.
     */
    public function canMoveTo(WorkflowInstance $instance, WorkflowStage&BackedEnum $stage): bool
    {
        $allowed = $this->definitionFor($instance)->transitions()[$instance->current_stage] ?? [];

        return in_array($stage->value, $allowed, strict: true);
    }

    /**
     * Why a move would be refused right now, as messages for the person attempting it.
     *
     * Exposed separately from `transitionTo` so the UI can disable an action and say why,
     * rather than offering it and then failing (OFF-CHK-001's banner, REC-PST-003's gate).
     *
     * @return list<string>
     */
    public function blockingReasons(WorkflowInstance $instance, WorkflowStage&BackedEnum $stage): array
    {
        $reasons = [];

        foreach ($this->definitionFor($instance)->guards()[$stage->value] ?? [] as $guardClass) {
            /** @var TransitionGuard $guard */
            $guard = app($guardClass);

            if (! $guard->passes($instance)) {
                $reasons[] = $guard->message($instance);
            }
        }

        return $reasons;
    }

    /**
     * Approve the step that is currently open (CORE-WFL-003).
     *
     * @throws StepNotActionable
     */
    public function approve(WorkflowStep $step, User $actor, ?string $note = null): WorkflowStep
    {
        $this->assertDecidable($step, $actor);

        return DB::transaction(function () use ($step, $actor, $note): WorkflowStep {
            $step->update([
                'status' => WorkflowStepStatus::Approved,
                'decided_by_user_id' => $actor->id,
                'decided_at' => now(),
                'note' => $note,
            ]);

            $this->openNextStep($step->instance);

            return $step->refresh();
        });
    }

    /**
     * Reject the step that is currently open. A reason is required, because every rejection in
     * the spec carries a note back to the requester (OFF-APR-006, REC-APR-006).
     *
     * @throws StepNotActionable
     */
    public function reject(WorkflowStep $step, User $actor, string $note): WorkflowStep
    {
        $this->assertDecidable($step, $actor);

        if (trim($note) === '') {
            throw new \InvalidArgumentException('A rejection needs a reason.');
        }

        return DB::transaction(function () use ($step, $actor, $note): WorkflowStep {
            $step->update([
                'status' => WorkflowStepStatus::Rejected,
                'decided_by_user_id' => $actor->id,
                'decided_at' => now(),
                'note' => $note,
            ]);

            // Later steps stay locked: one rejection stops the chain rather than letting the
            // next approver act on a decision that has already gone against the request.
            return $step->refresh();
        });
    }

    /**
     * The step the instance is waiting on, or null when the chain is finished or stopped
     * (CORE-WFL-008).
     */
    public function waitingOn(WorkflowInstance $instance): ?WorkflowStep
    {
        return $instance->steps()->where('status', WorkflowStepStatus::Pending)->first();
    }

    /**
     * Whether every step in the chain was approved — the usual guard before advancing a stage.
     */
    public function chainIsApproved(WorkflowInstance $instance): bool
    {
        return $instance->steps()->exists()
            && ! $instance->steps()
                ->where('status', '!=', WorkflowStepStatus::Approved->value)
                ->exists();
    }

    public function wasRejected(WorkflowInstance $instance): bool
    {
        return $instance->steps()->where('status', WorkflowStepStatus::Rejected)->exists();
    }

    /**
     * Add a comment to the thread, tagged with the stage it was written in (CORE-WFL-007).
     */
    public function comment(WorkflowInstance $instance, User $author, string $body): WorkflowComment
    {
        return $instance->comments()->create([
            'user_id' => $author->id,
            'stage' => $instance->current_stage,
            'body' => $body,
        ]);
    }

    /**
     * How long the instance spent in each stage it has passed through (CORE-WFL-006).
     *
     * The last entry is the stage it is in now, measured to this moment, which is what the
     * stage-by-stage timeline on close needs (OFF-PIP-007).
     *
     * @return list<array{stage: string, entered_at: Carbon, seconds: int}>
     */
    public function stageDurations(WorkflowInstance $instance): array
    {
        $transitions = $instance->transitions()->get();
        $durations = [];

        foreach ($transitions as $index => $transition) {
            $next = $transitions->get($index + 1);
            $until = $next !== null
                ? $next->created_at
                : ($instance->completed_at ?? now());

            $durations[] = [
                'stage' => $transition->to_stage,
                'entered_at' => $transition->created_at,
                'seconds' => (int) $transition->created_at->diffInSeconds($until, absolute: true),
            ];
        }

        return $durations;
    }

    /**
     * The definition behind an instance.
     *
     * Resolved through the engine's own registry rather than the model's convenience accessor,
     * so the dependency is explicit and a test can swap the registry.
     */
    private function definitionFor(WorkflowInstance $instance): WorkflowDefinition
    {
        return $this->registry->get($instance->definition);
    }

    /**
     * @param  list<StepBlueprint>  $steps
     */
    private function buildChain(WorkflowInstance $instance, array $steps): void
    {
        foreach ($steps as $index => $blueprint) {
            $instance->steps()->create([
                'company_id' => $instance->company_id,
                'position' => $index + 1,
                'label' => $blueprint->label,
                'approver_role' => $blueprint->approverRole,
                'approver_user_id' => $blueprint->approverUserId,
                // Only the first step opens; the rest wait their turn, which is what makes
                // the sign-off sequential rather than a free-for-all (OFF-SIG-005).
                'status' => $index === 0 ? WorkflowStepStatus::Pending : WorkflowStepStatus::Locked,
            ]);
        }
    }

    private function openNextStep(WorkflowInstance $instance): void
    {
        $next = $instance->steps()
            ->where('status', WorkflowStepStatus::Locked)
            ->orderBy('position')
            ->first();

        $next?->update(['status' => WorkflowStepStatus::Pending]);
    }

    /**
     * @throws StepNotActionable
     */
    private function assertDecidable(WorkflowStep $step, User $actor): void
    {
        if ($step->status === WorkflowStepStatus::Locked) {
            throw StepNotActionable::locked($step);
        }

        if ($step->status->isDecided()) {
            throw StepNotActionable::alreadyDecided($step);
        }

        if (! $step->canBeDecidedBy($actor)) {
            throw StepNotActionable::notTheApprover($step);
        }
    }
}
