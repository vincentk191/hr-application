<?php

namespace App\Console\Commands;

use App\Enums\RequisitionStage;
use App\Mail\CandidateReminder;
use App\Models\Candidate;
use App\Models\Requisition;
use App\Support\Notifications\Notifier;
use App\Support\Recruitment\InvitationMessage;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Mail;

/**
 * The interview reminders (REC-INT), run every morning. A candidate with an email is emailed the day before; on the
 * day itself HR and the new hire's direct superior get a notice listing the interviews; the morning after, and until it
 * is done, the same two are told which interviews are still waiting for an evaluation. Each is sent once: running the
 * command again changes nothing, and a re-invite with a new time clears them. Requisitions that are cancelled or filled
 * are left alone.
 */
#[Signature('recruitment:interview-reminders')]
#[Description('Remind candidates of tomorrow\'s interviews, HR of today\'s, and ask for the evaluations still missing')]
class SendInterviewReminders extends Command
{
    public function handle(Notifier $notifier): int
    {
        $zone = (string) config('attendance.device_timezone');
        $today = CarbonImmutable::now($zone)->startOfDay();

        $emailed = $this->remindCandidates($today->addDay(), $zone);
        $noticed = $this->remindInterviewers($today, $notifier);
        $chased = $this->askForEvaluations($today, $notifier);

        $this->info("{$emailed} candidate(s) emailed, {$noticed} interview(s) announced, {$chased} awaiting an evaluation.");

        return self::SUCCESS;
    }

    /**
     * Candidates whose interview is on the given day and who have an email.
     */
    private function remindCandidates(CarbonImmutable $day, string $zone): int
    {
        $count = 0;

        $this->on($day)->whereNull('candidate_reminded_at')->whereNotNull('email')->with('requisition.company')
            ->each(function (Candidate $candidate) use (&$count): void {
                $requisition = $candidate->requisition;

                Mail::to((string) $candidate->email)->queue(new CandidateReminder(
                    'Pengingat interview besok — '.$requisition->position_name,
                    InvitationMessage::reminder($candidate, $requisition),
                ));

                $candidate->forceFill(['candidate_reminded_at' => now()])->save();
                $count++;
            });

        return $count;
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
     * Candidates with an interview time, on requisitions that are at Interview or Offer.
     *
     * @return Builder<Candidate>
     */
    private function live(): Builder
    {
        return Candidate::query()->acrossCompanies()
            ->whereNotNull('interview_at')
            ->whereHas('requisition', fn (Builder $requisition) => $requisition->withoutGlobalScopes()
                ->whereHas('workflow', fn (Builder $workflow) => $workflow->withoutGlobalScopes()
                    ->whereIn('current_stage', [RequisitionStage::Interview->value, RequisitionStage::Offer->value])));
    }
}
