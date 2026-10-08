<?php

namespace App\Support\Notifications;

use App\Models\Employee;
use App\Models\EmployeeDocument;
use App\Models\LeaveRequest;
use App\Models\User;
use App\Models\WorkflowStep;
use App\Notifications\InAppNotice;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Notifications\DatabaseNotification;

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
            "{$leave->leave_type->label()} · {$leave->start_on->toDateString()} – {$leave->end_on->toDateString()} · {$leave->days} hari kerja · menunggu {$waitingOn}",
            route('leave-requests.index', ['open' => $leave->id], absolute: false),
        );

        foreach ($this->hrAdmins($leave->company_id) as $user) {
            if ($user->isNot($actor) && ! $approvers->contains('id', $user->id)) {
                $user->notify($notice);
            }
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
            "{$employee} · {$leave->leave_type->label()} · {$leave->start_on->toDateString()} – {$leave->end_on->toDateString()} · {$leave->days} hari kerja",
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

        $body = "{$leave->leave_type->label()} · {$leave->start_on->toDateString()} – {$leave->end_on->toDateString()}"
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
     * Send a notification unless this person already has one with the same key.
     */
    public function once(?User $user, InAppNotice $notice): void
    {
        if ($user === null || ! $user->is_active) {
            return;
        }

        if ($notice->key !== null && $this->alreadySent($user, $notice->key)) {
            return;
        }

        $user->notify($notice);
    }

    private function alreadySent(User $user, string $key): bool
    {
        return DatabaseNotification::query()
            ->where('notifiable_type', $user->getMorphClass())
            ->where('notifiable_id', $user->getKey())
            ->where('data', 'like', '%"key":'.str_replace(['%', '_'], ['\\%', '\\_'], (string) json_encode($key)).'%')
            ->exists();
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
     * @return Collection<int, User>
     */
    private function documentManagers(int $companyId): Collection
    {
        return User::query()->where('is_active', true)->get()
            ->filter(fn (User $user): bool => $user->can('manage-documents') && $user->canAccessCompany($companyId))
            ->values();
    }
}
