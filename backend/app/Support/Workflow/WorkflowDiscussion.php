<?php

namespace App\Support\Workflow;

use App\Enums\Role;
use App\Models\User;
use App\Models\WorkflowComment;
use App\Models\WorkflowInstance;
use Closure;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The comment thread of a workflow, as the side drawer shows it (CORE-WFL-007): every comment since the
 * case began, each tagged with the stage it was written in, and what is new to the person looking.
 *
 * Shared by every flow that runs on the engine (offboarding now, recruitment and others later): a flow
 * supplies its stage labels and, if it wants, a badge for each author ("Pemohon", "Atasan"). The thread
 * is never cleared when the case moves on; it persists through every stage.
 */
class WorkflowDiscussion
{
    /**
     * @param  array<string, string>  $stageLabels  stage value => label, for the stage chip on a comment
     * @param  (Closure(User): (string|null))|null  $badgeFor  a short badge for an author, e.g. "PEMOHON"
     * @return array{items: list<array<string, mixed>>, unread: int, unreadByStage: array<string, int>}
     */
    public function present(WorkflowInstance $instance, User $viewer, array $stageLabels, ?Closure $badgeFor = null): array
    {
        $lastRead = $this->lastRead($instance, $viewer);

        $items = $instance->comments()->with('author:id,name')->orderBy('id')->get()
            ->map(function (WorkflowComment $comment) use ($viewer, $lastRead, $stageLabels, $badgeFor): array {
                $mine = $comment->user_id === $viewer->id;
                $stage = (string) $comment->stage;

                return [
                    'id' => $comment->id,
                    'author' => $comment->author?->name,
                    'role' => $comment->author === null ? null : $this->roleLabel($comment->author),
                    'badge' => $comment->author === null || $badgeFor === null ? null : $badgeFor($comment->author),
                    'body' => $comment->body,
                    'at' => Carbon::parse($comment->created_at)->toDateTimeString(),
                    'stage' => $stage,
                    'stageLabel' => $stageLabels[$stage] ?? $this->fallbackStageLabel($stage),
                    'mine' => $mine,
                    'isNew' => ! $mine && $comment->id > $lastRead,
                ];
            })->all();
        $items = array_values($items);

        $new = array_filter($items, fn (array $item): bool => $item['isNew']);
        $byStage = [];

        foreach ($new as $item) {
            $byStage[$item['stage']] = ($byStage[$item['stage']] ?? 0) + 1;
        }

        return ['items' => $items, 'unread' => count($new), 'unreadByStage' => $byStage];
    }

    /**
     * The viewer has now seen everything written so far.
     */
    public function markRead(WorkflowInstance $instance, User $viewer): void
    {
        $newest = (int) $instance->comments()->max('id');

        DB::table('workflow_comment_reads')->upsert(
            [['user_id' => $viewer->id, 'workflow_instance_id' => $instance->id, 'last_read_comment_id' => $newest, 'created_at' => now(), 'updated_at' => now()]],
            ['user_id', 'workflow_instance_id'],
            ['last_read_comment_id', 'updated_at'],
        );
    }

    private function lastRead(WorkflowInstance $instance, User $viewer): int
    {
        return (int) DB::table('workflow_comment_reads')
            ->where('user_id', $viewer->id)
            ->where('workflow_instance_id', $instance->id)
            ->value('last_read_comment_id');
    }

    private function roleLabel(User $user): ?string
    {
        $name = $user->getRoleNames()->first();

        return $name === null ? null : (Role::tryFrom($name)?->label() ?? $name);
    }

    /**
     * A comment copied over from a settled step carries the step's outcome as its stage.
     */
    private function fallbackStageLabel(string $stage): string
    {
        return match ($stage) {
            'rejected' => 'Penolakan',
            'approved' => 'Persetujuan',
            '' => '—',
            default => Str::headline($stage),
        };
    }
}
