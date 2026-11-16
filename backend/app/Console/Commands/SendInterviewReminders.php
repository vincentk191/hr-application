<?php

namespace App\Console\Commands;

use App\Enums\RequisitionStage;
use App\Models\Candidate;
use App\Models\Requisition;
use App\Support\Notifications\Notifier;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

/**
 * The interview reminders (REC-INT), run every morning. On the day itself HR and the new hire's direct superior get a
 * notice listing the interviews; the morning after, and until it is done, the same two are told which interviews are
 * still waiting for an evaluation. Each is sent once: running the command again changes nothing, and a re-invite with a
 * new time clears them. Requisitions that are cancelled or filled are left alone. The candidate is not reminded by email:
 * the Google Calendar invitation does that.
 */
#[Signature('recruitment:interview-reminders')]
#[Description('Remind HR of today\'s interviews, and ask for the evaluations still missing')]
class SendInterviewReminders extends Command
{
    public function handle(Notifier $notifier): int
    {
        $today = CarbonImmutable::now((string) config('attendance.device_timezone'))->startOfDay();

        $noticed = $this->remindInterviewers($today, $notifier);
        $chased = $this->askForEvaluations($today, $notifier);

        $this->info("{$noticed} interview(s) announced, {$chased} awaiting an evaluation.");

        return self::SUCCESS;
    }

    /**
     * Interviews on the given day, announced once per requisition to HR and the direct superior.
     */
    private function remindInterviewers(CarbonImmutable $day, Notifier $notifier): int
    {
        $due = $this->on($day)->whereNull('interviewers_reminded_at')->with('requisition.company')->get();
        $count = 0;

        foreach ($due->groupBy('requisition_id') as $candidates) {
            $notifier->interviewsToday($candidates->first()->requisition, $candidates);

            foreach ($candidates as $candidate) {
                $candidate->forceFill(['interviewers_reminded_at' => now()])->save();
                $count++;
            }
        }

        return $count;
    }

    /**
     * Interviews from before today that nobody has evaluated yet, announced once per requisition to HR and the direct
     * superior. The same test as the tab's "Menunggu penilaian": held, and no evaluation written.
     */
    private function askForEvaluations(CarbonImmutable $today, Notifier $notifier): int
    {
        $due = $this->live()
            ->where('interview_at', '<', $today->startOfDay()->utc())
            ->whereNull('evaluation_prompted_at')
            ->whereDoesntHave('evaluations')
            ->with('requisition.company')
            ->get();
        $count = 0;

        foreach ($due->groupBy('requisition_id') as $candidates) {
            $notifier->evaluationsDue($candidates->first()->requisition, $candidates);

            foreach ($candidates as $candidate) {
                $candidate->forceFill(['evaluation_prompted_at' => now()])->save();
                $count++;
            }
        }

        return $count;
    }

    /**
     * Candidates of live requisitions (at Interview or Offer) whose interview falls on the given company day.
     *
     * @return Builder<Candidate>
     */
    private function on(CarbonImmutable $day): Builder
    {
        return $this->live()->whereBetween('interview_at', [$day->startOfDay()->utc(), $day->endOfDay()->utc()]);
    }

    /**
     * Candidates still in the process (not turned down) with an interview time, on requisitions at Interview or Offer.
     *
     * @return Builder<Candidate>
     */
    private function live(): Builder
    {
        return Candidate::query()->acrossCompanies()
            ->inProcess()
            ->whereNotNull('interview_at')
            ->whereHas('requisition', fn (Builder $requisition) => $requisition->withoutGlobalScopes()
                ->whereHas('workflow', fn (Builder $workflow) => $workflow->withoutGlobalScopes()
                    ->whereIn('current_stage', [RequisitionStage::Interview->value, RequisitionStage::Offer->value])));
    }
}
