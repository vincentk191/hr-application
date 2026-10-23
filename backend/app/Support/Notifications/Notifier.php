<?php

namespace App\Support\Notifications;

use App\Enums\Permission;
use App\Models\Employee;
use App\Models\EmployeeDocument;
use App\Models\LeaveRequest;
use App\Models\OffboardingCase;
use App\Models\ProfileChangeRequest;
use App\Models\User;
use App\Models\WorkflowComment;
use App\Models\WorkflowStep;
use App\Notifications\InAppNotice;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Who is told what, and when. The only place that decides recipients, so the rules about who hears
 * of a leave request, an attendance issue or an expiring document are in one file.
 *
 * Everything is in-app (the database channel). The person who caused an event is never notified of
 * it: they just did it.
 */
class Notifier
{
    /**
     * A request has been filed, or filed again after a correction: tell whoever it now waits on, and
     * tell every HR Admin that it exists. HR do not approve leave, but they see all of it, so each
     * submission reaches them as information. Someone who is both the approver and HR is told once.
     */
    public function leaveSubmitted(LeaveRequest $leave, ?User $actor, bool $resubmitted = false): void
    {
        $leave->loadMissing('employee', 'workflow.steps');
        $step = $leave->workflow?->steps->firstWhere('status', 'pending');
        $approvers = $step instanceof WorkflowStep ? $this->approversOf($step, $leave->company_id) : new Collection;

        $this->leaveAwaitingApproval($leave, $actor, $resubmitted);

        $employee = $leave->employee->full_name;
        $waitingOn = $step instanceof WorkflowStep ? $step->label : '—';

        $notice = new InAppNotice(
            'leave',
            $resubmitted ? "{$leave->reference()} diajukan ulang oleh {$employee}" : "{$leave->reference()} diajukan oleh {$employee}",
            "{$leave->typeLabel()}{$this->timeframeSuffix($leave)} · {$leave->start_on->toDateString()} – {$leave->end_on->toDateString()} · {$leave->days} hari kerja · menunggu {$waitingOn}",
            route('leave-requests.index', ['open' => $leave->id], absolute: false),
        );

        foreach ($this->hrAdmins($leave->company_id) as $user) {
            if ($user->isNot($actor) && ! $approvers->contains('id', $user->id)) {
                $user->notify($notice);
            }
        }
    }

    /**
     * A request filed by the employee's own Atasan and approved on filing (REQ-WIZ-015): the employee
     * hears it is approved, and every HR Admin that it exists. Whoever filed it is not told.
     */
    public function leaveFiledAndApproved(LeaveRequest $leave, User $actor): void
    {
        $leave->loadMissing('employee.user');

        $this->leaveDecided($leave, true, null, $actor);

        $notice = new InAppNotice(
            'leave',
            "{$leave->reference()} diajukan dan disetujui atasan untuk {$leave->employee->full_name}",
            "{$leave->typeLabel()}{$this->timeframeSuffix($leave)} · {$leave->start_on->toDateString()} – {$leave->end_on->toDateString()} · {$leave->days} hari kerja",
            route('leave-requests.index', ['open' => $leave->id], absolute: false),
        );

        foreach ($this->hrAdmins($leave->company_id) as $user) {
            if ($user->isNot($actor) && $user->isNot($leave->employee->user)) {
                $user->notify($notice);
            }
        }
    }

    /**
     * An employee asked to change their profile: every HR Admin of the company hears of it, except
     * the employee themselves if they are one (EMP-CHG-006).
     */
    public function profileChangeSubmitted(ProfileChangeRequest $change, User $actor): void
    {
        $change->loadMissing('employee');

        $notice = new InAppNotice(
            'profile',
            "{$change->reference()}: {$change->employee->full_name} meminta perubahan data",
            count($change->changes).' data diubah, menunggu persetujuan HR.',
            route('profile-changes.index', ['open' => $change->id], absolute: false),
        );

        foreach ($this->hrAdmins($change->company_id) as $user) {
            if ($user->isNot($actor)) {
                $user->notify($notice);
            }
        }
    }

    /**
     * HR decided: tell the employee, with the reason on a rejection.
     */
    public function profileChangeDecided(ProfileChangeRequest $change, bool $approved, User $actor): void
    {
        $change->loadMissing('employee.user');
        $employeeUser = $change->employee?->user;

        if ($employeeUser === null || $employeeUser->is($actor) || ! $employeeUser->is_active) {
            return;
        }

        $skipped = $change->conflicts ?? [];

        $employeeUser->notify(new InAppNotice(
            'profile',
            $approved ? "{$change->reference()} disetujui" : "{$change->reference()} ditolak",
            $approved
                ? ($skipped === [] ? 'Perubahan data Anda sudah berlaku.' : 'Sebagian berlaku. Belum diterapkan karena HR sudah mengubahnya: '.implode(', ', $skipped).'.')
                : "Alasan: {$change->decision_note}",
            route('employees.changes.create', $change->employee_id, absolute: false),
        ));
    }

    /**
     * HR sent an offboarding case for approval: tell Management, who decide it (OFF-APR-001). The person
     * leaving is never told, even if they hold the Management role.
     */
    public function offboardingSubmitted(OffboardingCase $case, User $actor, bool $resubmitted = false): void
    {
        $case->loadMissing('employee.user', 'workflow.steps');
        $step = $case->workflow?->steps->firstWhere('status', 'pending');

        if (! $step instanceof WorkflowStep) {
            return;
        }

        $notice = new InAppNotice(
            'offboarding',
            $resubmitted ? "{$case->reference()} diajukan ulang: {$case->employee->full_name}" : "{$case->reference()}: offboarding {$case->employee->full_name} menunggu persetujuan",
            "{$case->exit_type?->label()} · hari terakhir {$case->last_working_on?->toDateString()}",
            route('offboarding.show', $case, absolute: false),
        );

        foreach ($this->approversOf($step, $case->company_id) as $user) {
            if ($user->isNot($actor) && $user->isNot($case->employee->user)) {
                $user->notify($notice);
            }
        }
    }

    /**
     * Management decided: tell HR who made the case, with the reason on a rejection. An approval also
     * tells the leaver's Atasan, who works the checklist from here (OFF-APR-006).
     */
    public function offboardingDecided(OffboardingCase $case, bool $approved, ?string $note, User $actor): void
    {
        $case->loadMissing('creator', 'employee.manager.user');
        $name = $case->employee->full_name;
        $url = route('offboarding.show', $case, absolute: false);

        $recipients = collect([$case->creator])->filter()->reject(fn (User $user): bool => $user->is($actor) || ! $user->is_active);

        foreach ($recipients as $user) {
            $user->notify(new InAppNotice(
                'offboarding',
                $approved ? "{$case->reference()} disetujui: {$name}" : "{$case->reference()} ditolak: {$name}",
                $approved ? 'Checklist offboarding dimulai.' : "Alasan: {$note}",
                $url,
            ));
        }

        $atasan = $case->employee->manager?->user;

        if ($approved && $atasan !== null && $atasan->is_active && $atasan->isNot($actor)) {
            $atasan->notify(new InAppNotice('offboarding', "Offboarding {$name} disetujui", 'Checklist dimulai; Anda memantau serah terima tim Anda.', $url));
        }
    }

    /**
     * Something happened on an offboarding case that these people should hear about. The person who did
     * it, the person leaving and anyone deactivated are left out.
     *
     * @param  iterable<User|null>  $recipients
     */
    public function offboardingEvent(OffboardingCase $case, iterable $recipients, User $actor, string $title, string $body): void
    {
        $case->loadMissing('employee');
        $leaver = $case->employee->user_id;

        $notice = new InAppNotice('offboarding', $title, $body, route('offboarding.show', $case, absolute: false));

        foreach (collect($recipients)->filter()->unique('id') as $user) {
            if ($user->is_active && $user->isNot($actor) && $user->id !== $leaver) {
                $user->notify($notice);
            }
        }
    }

    /**
     * The checklist stage began: tell the owner of each department's part, once, to pick what applies
     * (OFF-ITM-007). A department with nobody to own it is HR Admin's, and HR made the case.
     */
    public function accountRegistered(Employee $employee, User $user): void
    {
        $notice = new InAppNotice('account', 'Pendaftaran akun menunggu aktivasi', "{$employee->full_name} ({$employee->employee_number}) mendaftarkan akunnya. Periksa lalu aktifkan, atau tolak jika bukan orangnya.", route('employees.show', $employee, absolute: false));

        foreach ($this->hrAdmins($employee->company_id) as $hr) {
            $hr->notify($notice);
        }
    }

    public function offboardingChecklistStarted(OffboardingCase $case): void
    {
        $case->loadMissing('employee');
        $groups = $case->checklistDepartments()->with('owner')->get();

        foreach ($groups->pluck('owner')->filter()->unique('id') as $owner) {
            if (! $owner->is_active) {
                continue;
            }

            $owner->notify(new InAppNotice(
                'offboarding',
                "Offboarding {$case->employee->full_name}: pilih item untuk departemen Anda",
                "{$case->reference()} · hari terakhir {$case->last_working_on?->toDateString()}. Tandai item yang berlaku, lalu tekan Daftar selesai.",
                route('offboarding.show', $case, absolute: false),
            ));
        }
    }

    /**
     * Something moved on the checklist that HR, who made the case, should hear about.
     */
    public function offboardingChecklistProgress(OffboardingCase $case, User $actor, string $message): void
    {
        $case->loadMissing('creator', 'employee');

        if ($case->creator === null || $case->creator->is($actor) || ! $case->creator->is_active) {
            return;
        }

        $case->creator->notify(new InAppNotice(
            'offboarding',
            "{$case->reference()}: {$case->employee->full_name}",
            $message,
            route('offboarding.show', $case, absolute: false),
        ));
    }

    /**
     * A comment on a case: tell the others in the conversation, the requester and whoever decides it.
     */
    public function offboardingCommented(OffboardingCase $case, WorkflowComment $comment, User $author): void
    {
        $case->loadMissing('creator', 'employee.user', 'employee.manager.user', 'workflow.steps', 'workflow.comments.author');
        $step = $case->workflow?->steps->firstWhere('status', 'pending');

        // The requester, whoever decides next, the leaver's Atasan, and anyone who has already spoken.
        $recipients = collect([$case->creator, $case->employee->manager?->user])
            ->merge($step instanceof WorkflowStep ? $this->approversOf($step, $case->company_id) : [])
            ->merge($case->workflow?->comments->pluck('author') ?? [])
            ->filter()
            ->unique('id')
            ->reject(fn (User $user): bool => $user->is($author) || $user->is($case->employee->user) || ! $user->is_active);

        foreach ($recipients as $user) {
            $user->notify(new InAppNotice(
                'offboarding',
                "{$case->reference()}: komentar baru dari {$author->name}",
                mb_strimwidth($comment->body, 0, 140, '…'),
                route('offboarding.show', $case, absolute: false),
            ));
        }
    }

    /**
     * Tell whoever the request is now waiting on.
     */
    public function leaveAwaitingApproval(LeaveRequest $leave, ?User $actor, bool $resubmitted = false): void
    {
        $leave->loadMissing('employee', 'workflow.steps');
        $step = $leave->workflow?->steps->firstWhere('status', 'pending');

        if (! $step instanceof WorkflowStep) {
            return;
        }

        $employee = $leave->employee->full_name;

        $notice = new InAppNotice(
            'leave',
            $resubmitted ? "{$leave->reference()} diajukan ulang" : "{$leave->reference()} menunggu persetujuan Anda",
            "{$employee} · {$leave->typeLabel()}{$this->timeframeSuffix($leave)} · {$leave->start_on->toDateString()} – {$leave->end_on->toDateString()} · {$leave->days} hari kerja",
            route('leave-requests.index', ['open' => $leave->id], absolute: false),
        );

        foreach ($this->approversOf($step, $leave->company_id) as $user) {
            if ($user->isNot($actor)) {
                $user->notify($notice);
            }
        }
    }

    /**
     * Tell the person the request is for (and whoever filed it for them) how it was decided.
     */
    public function leaveDecided(LeaveRequest $leave, bool $approved, ?string $note, User $actor): void
    {
        $leave->loadMissing('employee.user', 'createdBy');

        $recipients = collect([$leave->employee?->user, $leave->createdBy])
            ->filter()
            ->unique('id')
            ->reject(fn (User $user): bool => $user->is($actor) || ! $user->is_active);

        $body = "{$leave->typeLabel()}{$this->timeframeSuffix($leave)} · {$leave->start_on->toDateString()} – {$leave->end_on->toDateString()}"
            .(! $approved && $note !== null && trim($note) !== '' ? " · “{$note}”" : '');

        foreach ($recipients as $user) {
            $user->notify(new InAppNotice(
                'leave',
                $approved ? "{$leave->reference()} disetujui" : "{$leave->reference()} ditolak",
                $body,
                $approved
                    ? route('employees.show', ['employee' => $leave->employee_id, 'tab' => 'requests'], absolute: false)
                    : route('leave-requests.edit', $leave, absolute: false),
            ));
        }
    }

    /**
     * Tell an employee, and their supervisor, about attendance exceptions on a day. One notice per
     * person per day, however many exceptions: the list is for the day, not for each scan.
     *
     * @param  list<string>  $labels  what was wrong, e.g. "Terlambat"
     */
    public function attendanceIssues(Employee $employee, string $date, array $labels): void
    {
        $employee->loadMissing('user', 'manager.user');
        $what = implode(', ', $labels);
        $month = substr($date, 0, 7);

        $this->once($employee->user, new InAppNotice(
            'attendance',
            "Masalah absensi {$date}",
            $what,
            route('employees.show', ['employee' => $employee->id, 'tab' => 'attendance', 'month' => $month], absolute: false),
            "issues:{$employee->id}:{$date}:self",
        ));

        $this->once($employee->manager?->user, new InAppNotice(
            'attendance',
            "{$employee->full_name}: masalah absensi {$date}",
            $what,
            route('employees.show', ['employee' => $employee->id], absolute: false),
            "issues:{$employee->id}:{$date}:lead",
        ));
    }

    /**
     * Tell the people who look after documents that one is about to lapse, or has. Once for each
     * stage, so a document is not announced every morning.
     */
    public function documentExpiry(EmployeeDocument $document, string $status): void
    {
        $document->loadMissing('employee');
        $name = $document->employee->full_name;
        $date = $document->expires_on?->toDateString();

        $notice = new InAppNotice(
            'document',
            $status === 'expired' ? "Dokumen kedaluwarsa: {$document->title}" : "Dokumen segera berakhir: {$document->title}",
            "{$name} · berlaku sampai {$date}",
            route('employees.show', ['employee' => $document->employee_id, 'tab' => 'documents'], absolute: false),
            "document:{$document->id}:{$status}:{$date}",
        );

        foreach ($this->documentManagers($document->company_id) as $user) {
            $this->once($user, $notice);
        }
    }

    /**
     * Send a notification unless this person has already been sent one with the same key.
     *
     * What was sent is remembered in `notification_keys`, not by looking for the notification:
     * read notifications are deleted after a day (`notifications:prune-read`), and a job that runs
     * every morning must not send the same thing again because its notification is gone. The insert
     * is the check, so two runs at once cannot both send.
     */
    public function once(?User $user, InAppNotice $notice): void
    {
        if ($user === null || ! $user->is_active) {
            return;
        }

        if ($notice->key !== null) {
            $first = DB::table('notification_keys')->insertOrIgnore([
                'user_id' => $user->getKey(),
                'key' => $notice->key,
                'created_at' => now(),
            ]);

            if ($first === 0) {
                return;
            }
        }

        $user->notify($notice);
    }

    /**
     * The people a step is waiting on: the named person, or whoever holds the role in that company.
     *
     * @return Collection<int, User>
     */
    private function approversOf(WorkflowStep $step, int $companyId): Collection
    {
        if ($step->approver_user_id !== null) {
            return User::query()->whereKey($step->approver_user_id)->where('is_active', true)->get();
        }

        if ($step->approver_role === null) {
            return new Collection;
        }

        return User::role($step->approver_role)->where('is_active', true)->get()
            ->filter(fn (User $user): bool => $user->canAccessCompany($companyId))
            ->values();
    }

    private function timeframeSuffix(LeaveRequest $leave): string
    {
        return $leave->timeframe() === null ? '' : " ({$leave->timeframe()})";
    }

    /**
     * @return Collection<int, User>
     */
    private function hrAdmins(int $companyId): Collection
    {
        return User::role('hr-admin')->where('is_active', true)->get()
            ->filter(fn (User $user): bool => $user->canAccessCompany($companyId))
            ->values();
    }

    /**
     * Chosen in the database: this runs once for every document that needs attention, so it must not
     * load every account and ask each one for its permissions.
     *
     * @return Collection<int, User>
     */
    private function documentManagers(int $companyId): Collection
    {
        return User::query()
            ->where('is_active', true)
            ->permission(Permission::ManageDocuments->value)
            ->accessingCompany($companyId)
            ->get();
    }
}
