<?php

namespace App\Http\Controllers;

use App\Actions\Recruitment\AddCandidate;
use App\Actions\Recruitment\AdvanceToInterview;
use App\Actions\Recruitment\AdvanceToOffer;
use App\Actions\Recruitment\AdvanceToPosting;
use App\Actions\Recruitment\AdvanceToScreening;
use App\Actions\Recruitment\CancelRequisition;
use App\Actions\Recruitment\ChangeDirectSuperior;
use App\Actions\Recruitment\DecideRequisition;
use App\Actions\Recruitment\InviteCandidate;
use App\Actions\Recruitment\MarkRequisitionFilled;
use App\Actions\Recruitment\RejectCandidate;
use App\Actions\Recruitment\RemoveCandidate;
use App\Actions\Recruitment\RemovePosting;
use App\Actions\Recruitment\RenewFormLink;
use App\Actions\Recruitment\RestoreCandidate;
use App\Actions\Recruitment\SaveApplicantCount;
use App\Actions\Recruitment\SaveEvaluation;
use App\Actions\Recruitment\SavePosting;
use App\Actions\Recruitment\SaveRequisitionDraft;
use App\Actions\Recruitment\SaveSalaryExpectation;
use App\Actions\Recruitment\SelectCandidate;
use App\Actions\Recruitment\SubmitRequisition;
use App\Actions\Recruitment\UnselectCandidate;
use App\Enums\EmploymentStatus;
use App\Enums\EmploymentType;
use App\Enums\Permission;
use App\Enums\RequisitionStage;
use App\Enums\Role;
use App\Http\Requests\AddCandidateRequest;
use App\Http\Requests\DecideRequisitionRequest;
use App\Http\Requests\InviteCandidateRequest;
use App\Http\Requests\SaveEvaluationRequest;
use App\Http\Requests\SavePostingRequest;
use App\Http\Requests\SaveRequisitionRequest;
use App\Models\Branch;
use App\Models\Candidate;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Position;
use App\Models\Requisition;
use App\Models\RequisitionPosting;
use App\Models\User;
use App\Support\CsvDownload;
use App\Support\Files\InlineImage;
use App\Support\Notifications\Notifier;
use App\Support\Recruitment\InterviewCalendar;
use App\Support\Recruitment\InvitationMessage;
use App\Support\Recruitment\RequisitionDepartments;
use App\Support\Recruitment\RequisitionPresenter;
use App\Support\Recruitment\RequisitionTemplate;
use App\Support\Workflow\WorkflowDiscussion;
use App\Workflows\Exceptions\TransitionBlocked;
use App\Workflows\WorkflowEngine;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Recruitment, first slice: a supervisor or HR raises a requisition, Management and then HR approve it, and HR
 * moves it on to posting (REC-DSH, REC-WIZ, REC-PIP, REC-APR). HR then records where the listing was posted by
 * hand and moves it on, or calls it off (REC-PST). The later stages come with their own slices.
 */
class RequisitionController extends Controller
{
    private const STATUSES = ['open', 'draft', 'submitted', 'posting', 'screening', 'interview', 'offer', 'filled', 'cancelled', 'all'];

    public function __construct(private RequisitionPresenter $presenter) {}

    /**
     * The recruitment dashboard (REC-DSH): the requisitions this person may see, in the company in view
     * (REC-DSH-009, through the company scope).
     */
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Requisition::class);

        $user = $request->user();
        $filters = $this->filters($request);
        $visible = fn (): Builder => Requisition::query()->visibleTo($user);

        $requisitions = $this->listed($user, $filters);
        $unread = app(WorkflowDiscussion::class)->unreadCounts($requisitions->pluck('workflow.id'), $user);

        $open = $visible()->with('workflow')->open()->get();

        return Inertia::render('recruitment/index', [
            'requisitions' => $requisitions->map(fn (Requisition $requisition): array => $this->presenter->row($requisition, $unread[$requisition->workflow?->id] ?? 0))->values(),
            'filters' => $filters,
            'summary' => [
                'open' => $open->count(),
                'awaitingApproval' => $open->filter(fn (Requisition $requisition): bool => $requisition->stage() === RequisitionStage::Submitted)->count(),
                'quota' => (int) $open->sum('quota'),
                'drafts' => Requisition::query()->where('created_by_user_id', $user->id)
                    ->whereHas('workflow', fn (Builder $workflow) => $workflow->where('current_stage', RequisitionStage::Draft->value))
                    ->count(),
            ],
            'departments' => Department::query()->whereIn('id', $visible()->select('department_id'))->orderBy('name')->pluck('name')->values(),
            'canCreate' => $user->can('create', Requisition::class),
        ]);
    }

    /**
     * The dashboard's requisitions as the filters narrow them, urgent ones first: what the table shows and what the
     * CSV export carries.
     *
     * @param  array{status: string, department: string|null}  $filters
     * @return Collection<int, Requisition>
     */
    private function listed(User $user, array $filters): Collection
    {
        return Requisition::query()->visibleTo($user)
            ->with(['department:id,name', 'branch:id,name', 'creator:id,name', 'workflow.steps', 'postings', 'candidates:id,requisition_id', 'offers.workflow'])
            ->when($filters['status'] === 'open', fn (Builder $query) => $query->open())
            ->when(! in_array($filters['status'], ['open', 'all'], true), fn (Builder $query) => $query->whereHas('workflow', fn (Builder $workflow) => $workflow->where('current_stage', $filters['status'])))
            ->when($filters['department'] !== null, fn (Builder $query) => $query->whereHas('department', fn (Builder $department) => $department->where('name', $filters['department'])))
            ->orderByDesc('is_urgent')
            ->latest('id')
            ->get();
    }

    /**
     * The dashboard as a CSV (REC-DSH-007): the same rows as on screen, with the same filters. The salary expectation
     * is added only for Management and HR, who are the only ones shown it.
     */
    public function export(Request $request): StreamedResponse
    {
        $this->authorize('viewAny', Requisition::class);

        $user = $request->user();
        $withSalary = $user->can(Permission::ManageRecruitment->value) || $user->isA(Role::Management);

        $rows = $this->listed($user, $this->filters($request))->map(function (Requisition $requisition) use ($withSalary): array {
            $row = $this->presenter->row($requisition);

            return [
                $row['reference'],
                $row['positionName'],
                $row['department'],
                $row['branch'],
                $row['quota'],
                $row['filled'],
                $row['candidates'],
                $row['stageLabel'],
                $row['statusLabel'],
                $row['isUrgent'] ? 'Ya' : '',
                $row['lastUpdate'],
                $row['createdBy'],
                ...($withSalary ? [$requisition->salary_min, $requisition->salary_max] : []),
            ];
        });

        return CsvDownload::stream('rekrutmen-'.now()->format('Y-m-d').'.csv', [
            ['Referensi', 'Posisi', 'Departemen', 'Cabang', 'Kuota', 'Terisi', 'Shortlist', 'Tahap', 'Status', 'Mendesak', 'Diperbarui', 'PIC', ...($withSalary ? ['Gaji minimal', 'Gaji maksimal'] : [])],
            ...$rows,
        ]);
    }

    /**
     * A file for sending the interview invitations from HR's own mailbox with a mail merge: one row per invited candidate
     * who has an email, with the subject and the text already written for that person, so each gets their own message
     * and nobody is in CC. The personal link to the candidate form is left out, since it is shown only when the
     * invitation is made, so the text is the invitation without the form paragraph. The note HR wrote is kept with
     * the candidate and goes in. The file holds candidates' emails, so each download is written to the audit trail.
     */
    public function exportInterviewees(Request $request, Requisition $requisition): StreamedResponse
    {
        $this->authorize('viewCandidates', $requisition);

        $requisition->loadMissing('company');
        $subject = InvitationMessage::subject($requisition);

        $candidates = $requisition->candidates()
            ->inProcess()
            ->whereNotNull('interview_at')
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->orderBy('interview_at')
            ->get();

        activity('recruitment')
            ->performedOn($requisition)
            ->causedBy($request->user())
            ->withProperties(['candidates' => $candidates->count()])
            ->log('Berkas undangan interview diunduh');

        $rows = $candidates
            ->map(fn (Candidate $candidate): array => [
                $candidate->name,
                $candidate->email,
                $requisition->position_name,
                InvitationMessage::dayLabel($candidate->interview_at),
                InvitationMessage::local($candidate->interview_at)->format('H:i'),
                $candidate->interview_mode === 'online' ? 'Daring' : 'Tatap muka',
                $candidate->interview_place,
                $subject,
                InvitationMessage::text($candidate, $requisition, null, $candidate->invitation_note),
                $candidate->invitation_emailed ? 'Ya' : 'Tidak',
            ]);

        return CsvDownload::stream('undangan-interview-'.$requisition->reference().'-'.now()->format('Y-m-d').'.csv', [
            ['Nama', 'Email', 'Posisi', 'Hari dan tanggal', 'Jam (WIB)', 'Format', 'Tempat atau tautan', 'Subjek', 'Pesan', 'Sudah diemail aplikasi'],
            ...$rows,
        ]);
    }

    /**
     * "Tambah ke kalender": one candidate's interview as an `.ics` file (REC-INT). HR gets the candidate among the
     * participants when they have an email; the direct superior, who is not shown candidates' emails, does not.
     */
    public function interviewEvent(Request $request, Requisition $requisition, Candidate $candidate, InterviewCalendar $calendar): HttpResponse
    {
        $this->authorize('exportInterviewEvent', [$requisition, $candidate]);

        $user = $request->user();
        $content = $calendar->forInterviewers($candidate, $requisition, $user, withCandidateEmail: $user->can('viewCandidates', $requisition));

        activity('recruitment')
            ->performedOn($requisition)
            ->causedBy($user)
            ->withProperties(['candidate' => $candidate->name])
            ->log('Jadwal interview diunduh');

        return response($content, 200, [
            'Content-Type' => 'text/calendar; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$calendar->filename($candidate).'"',
        ]);
    }

    /**
     * @return array{status: string, department: string|null}
     */
    private function filters(Request $request): array
    {
        $status = $request->string('status')->toString();

        return [
            'status' => in_array($status, self::STATUSES, true) ? $status : 'open',
            'department' => trim($request->string('department')->toString()) ?: null,
        ];
    }

    /**
     * The wizard for a new requisition (REC-WIZ).
     */
    public function create(Request $request): Response
    {
        $this->authorize('create', Requisition::class);

        return Inertia::render('recruitment/form', [
            'requisition' => null,
            ...$this->formOptions($request->user()),
        ]);
    }

    public function store(SaveRequisitionRequest $request, SaveRequisitionDraft $save, SubmitRequisition $submit): RedirectResponse
    {
        $requisition = $save->handle(null, $request->requisitionFields(), $request->user());

        if ($request->boolean('send')) {
            return $this->send($request->user(), $requisition, $submit);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => "Draft {$requisition->reference()} disimpan."]);

        return to_route('recruitment.index');
    }

    /**
     * Continue a draft at the step it was left on (REC-WIZ-004), or correct one already sent that nobody has
     * decided yet (REC-WIZ-005). A rejected requisition comes back here as a draft.
     */
    public function edit(Request $request, Requisition $requisition): Response|RedirectResponse
    {
        $this->authorize('view', $requisition);

        if ($request->user()->cannot('update', $requisition)) {
            return to_route('recruitment.show', $requisition);
        }

        return Inertia::render('recruitment/form', [
            'requisition' => $this->presenter->detail($requisition, $request->user()),
            // A requisition sent back is corrected here, so the reason is a click away.
            'discussion' => fn (): array => $this->discussion($requisition, $request->user()),
            ...$this->formOptions($request->user()),
        ]);
    }

    public function update(SaveRequisitionRequest $request, Requisition $requisition, SaveRequisitionDraft $save, SubmitRequisition $submit): RedirectResponse
    {
        $save->handle($requisition, $request->requisitionFields(), $request->user());

        if ($request->boolean('send') && $requisition->isDraft()) {
            return $this->send($request->user(), $requisition, $submit);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => $requisition->isDraft() ? 'Draft disimpan.' : 'Perubahan disimpan.']);

        return $requisition->isDraft() ? to_route('recruitment.index') : to_route('recruitment.show', $requisition);
    }

    /**
     * Send what was just saved for approval. A requisition that is not complete stays a saved draft, and the
     * wizard reopens on it with what is missing, so nothing typed is lost.
     */
    private function send(User $by, Requisition $requisition, SubmitRequisition $submit): RedirectResponse
    {
        try {
            $submit->handle($requisition, $by);
        } catch (ValidationException $e) {
            return to_route('recruitment.edit', $requisition)->withErrors($e->errors());
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => "{$requisition->reference()} diajukan untuk persetujuan."]);

        return to_route('recruitment.show', $requisition);
    }

    public function destroy(Requisition $requisition): RedirectResponse
    {
        $this->authorize('delete', $requisition);

        $requisition->workflow?->delete();
        $requisition->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Draft dihapus.']);

        return to_route('recruitment.index');
    }

    /**
     * The requisition on its pipeline (REC-PIP), opening on its current stage (REC-PIP-005). A draft opens the
     * wizard instead (REC-DSH-005).
     */
    public function show(Request $request, Requisition $requisition): Response|RedirectResponse
    {
        $this->authorize('view', $requisition);

        if ($requisition->isDraft() && $request->user()->can('update', $requisition)) {
            return to_route('recruitment.edit', $requisition);
        }

        return Inertia::render('recruitment/show', [
            'requisition' => $this->presenter->detail($requisition, $request->user()),
            // Its own prop, so the drawer can poll for new comments without reloading the whole requisition.
            'discussion' => fn (): array => $this->discussion($requisition, $request->user()),
        ]);
    }

    public function decide(DecideRequisitionRequest $request, Requisition $requisition, DecideRequisition $decide): RedirectResponse
    {
        if ($request->isApproval()) {
            $decide->approve(
                $requisition,
                $request->user(),
                $request->input('note'),
                $request->filled('salary_min') ? $request->integer('salary_min') : null,
                $request->filled('salary_max') ? $request->integer('salary_max') : null,
            );

            Inertia::flash('toast', ['type' => 'success', 'message' => "{$requisition->reference()} disetujui."]);
        } else {
            $decide->reject($requisition, $request->user(), (string) $request->input('note'));

            Inertia::flash('toast', ['type' => 'success', 'message' => "{$requisition->reference()} ditolak dan dikembalikan ke pemohon."]);
        }

        return back();
    }

    /**
     * "Simpan": Management keeps the salary expectation without approving yet (REC-APR-007).
     */
    public function salary(Request $request, Requisition $requisition, SaveSalaryExpectation $save): RedirectResponse
    {
        $this->authorize('saveSalary', $requisition);

        $validated = $request->validate(DecideRequisitionRequest::salaryRules(), DecideRequisitionRequest::salaryMessages());

        $save->handle($requisition, $request->user(), $validated['salary_min'] ?? null, $validated['salary_max'] ?? null);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Ekspektasi gaji disimpan.']);

        return back();
    }

    /**
     * "Lanjut ke Posting ›" (REC-APR-003), refused while an approval is missing.
     */
    public function advance(Request $request, Requisition $requisition, AdvanceToPosting $advance): RedirectResponse
    {
        $this->authorize('advance', $requisition);

        try {
            $advance->handle($requisition, $request->user());
        } catch (TransitionBlocked $blocked) {
            throw ValidationException::withMessages(['advance' => $blocked->getMessage()]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => "{$requisition->reference()} lanjut ke tahap Posting."]);

        return back();
    }

    /**
     * "Tandai sudah diposting": HR records that the listing is up on a platform (REC-PST-002).
     */
    public function storePosting(SavePostingRequest $request, Requisition $requisition, SavePosting $save): RedirectResponse
    {
        $posting = $save->handle($requisition, $request->postingFields(), $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => "Tercatat diposting di {$posting->platformLabel()}."]);

        return back();
    }

    /**
     * Correct where, when or the link of a recorded posting (REC-PST-002).
     */
    public function updatePosting(SavePostingRequest $request, Requisition $requisition, RequisitionPosting $posting, SavePosting $save): RedirectResponse
    {
        $save->handle($requisition, $request->postingFields(), $request->user(), $posting);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Catatan posting diperbarui.']);

        return back();
    }

    public function destroyPosting(Requisition $requisition, RequisitionPosting $posting, RemovePosting $remove): RedirectResponse
    {
        $this->authorize('managePostings', $requisition);

        $remove->handle($posting);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Catatan posting dihapus.']);

        return back();
    }

    /**
     * "Lanjut ke Screening ›" (REC-PST-003), refused until the listing is recorded on a platform.
     */
    public function advanceToScreening(Request $request, Requisition $requisition, AdvanceToScreening $advance): RedirectResponse
    {
        $this->authorize('managePostings', $requisition);

        try {
            $advance->handle($requisition, $request->user());
        } catch (TransitionBlocked $blocked) {
            throw ValidationException::withMessages(['advance' => $blocked->getMessage()]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => "{$requisition->reference()} lanjut ke tahap Screening."]);

        return back();
    }

    /**
     * HR enters the total number of applicants, across every platform (REC-SCR). Only the number is kept.
     */
    public function updateApplicants(Request $request, Requisition $requisition, SaveApplicantCount $save): RedirectResponse
    {
        $this->authorize('manageScreening', $requisition);

        $validated = $request->validate(
            ['applicants_count' => ['required', 'integer', 'min:0', 'max:100000']],
            ['applicants_count.required' => 'Isi jumlah pelamar.', 'applicants_count.integer' => 'Jumlah pelamar harus angka bulat.', 'applicants_count.min' => 'Jumlah pelamar tidak boleh negatif.', 'applicants_count.max' => 'Jumlah pelamar terlalu besar.'],
        );

        $save->handle($requisition, (int) $validated['applicants_count']);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Jumlah pelamar disimpan.']);

        return back();
    }

    /**
     * "Tambah ke shortlist": HR shortlists a candidate from what the platform showed (REC-SCR).
     */
    public function storeCandidate(AddCandidateRequest $request, Requisition $requisition, AddCandidate $add): RedirectResponse
    {
        $candidate = $add->handle(
            $requisition,
            $request->candidateFields(),
            $request->user(),
            $request->file('cv'),
            $request->boolean('confirm_duplicate'),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => "{$candidate->name} masuk shortlist."]);

        return back();
    }

    public function destroyCandidate(Requisition $requisition, Candidate $candidate, RemoveCandidate $remove): RedirectResponse
    {
        $this->authorize('removeCandidate', [$requisition, $candidate]);

        $remove->handle($candidate, request()->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Kandidat dihapus dari shortlist.']);

        return back();
    }

    /**
     * "Undang": invite a shortlisted candidate to interview (REC-INT). The invitation is emailed when the candidate has
     * an email, and always handed back once (with its personal link) so HR can paste it into WhatsApp.
     */
    public function inviteCandidate(InviteCandidateRequest $request, Requisition $requisition, Candidate $candidate, InviteCandidate $invite): RedirectResponse
    {
        abort_if($candidate->isRejected(), 403);

        $result = $invite->handle($requisition, $candidate, $request->invitationFields(), $request->user());

        Inertia::flash('invitation', [
            'candidateId' => $candidate->id,
            'name' => $candidate->name,
            'email' => $result['emailed'] ? $candidate->email : null,
            'link' => $result['link'],
            'message' => $result['message'],
            'expiresOn' => $result['expiresAt'] === null ? null : InvitationMessage::dayLabel($result['expiresAt'], false),
        ]);
        Inertia::flash('toast', ['type' => 'success', 'message' => "{$candidate->name} diundang interview."]);

        return back();
    }

    /**
     * "Tolak": HR turns a candidate down, with a reason, and may email them (REC-CAN-003).
     */
    public function rejectCandidate(Request $request, Requisition $requisition, Candidate $candidate, RejectCandidate $reject): RedirectResponse
    {
        $this->authorize('rejectCandidate', [$requisition, $candidate]);

        $validated = $request->validate([
            'note' => ['required', 'string', 'max:1000'],
            'notify_candidate' => ['nullable', 'boolean'],
        ], ['note.required' => 'Tulis alasan penolakan.']);

        $emailed = $reject->handle($requisition, $candidate, $request->user(), $validated['note'], $request->boolean('notify_candidate'));

        Inertia::flash('toast', ['type' => 'success', 'message' => "{$candidate->name} ditolak".($emailed ? ' dan dikabari lewat email.' : '.')]);

        return back();
    }

    /**
     * "Pulihkan": HR takes a rejection back (REC-CAN-003).
     */
    public function restoreCandidate(Request $request, Requisition $requisition, Candidate $candidate, RestoreCandidate $restore): RedirectResponse
    {
        $this->authorize('restoreCandidate', [$requisition, $candidate]);

        $restore->handle($requisition, $candidate, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => "{$candidate->name} kembali diproses."]);

        return back();
    }

    /**
     * "Ganti atasan": HR names a different direct superior (REC-PIP), who must be an active employee of the company with
     * an active account, since only they can choose the candidate.
     */
    public function changeSuperior(Request $request, Requisition $requisition, ChangeDirectSuperior $change): RedirectResponse
    {
        $this->authorize('changeSuperior', $requisition);

        $validated = $request->validate([
            'manager_employee_id' => ['required', 'integer'],
            'note' => ['nullable', 'string', 'max:500'],
        ], ['manager_employee_id.required' => 'Pilih atasan langsung.']);

        $superior = Employee::acrossCompanies()->with('user')
            ->whereKey($validated['manager_employee_id'])
            ->whereKeyNot($requisition->manager_employee_id ?? 0)
            ->where('company_id', $requisition->company_id)
            ->where('employment_status', EmploymentStatus::Active)
            ->whereHas('user', fn ($user) => $user->where('is_active', true))
            ->first();

        if (! $superior instanceof Employee) {
            throw ValidationException::withMessages(['manager_employee_id' => 'Pilih karyawan aktif yang punya akun, selain atasan saat ini.']);
        }

        $change->handle($requisition, $superior, $request->user(), $validated['note'] ?? null);

        Inertia::flash('toast', ['type' => 'success', 'message' => "Atasan langsung diganti ke {$superior->full_name}."]);

        return back();
    }

    /**
     * "Pilih": the direct superior chooses the candidate they prefer (REC-PIP).
     */
    public function selectCandidate(Request $request, Requisition $requisition, Candidate $candidate, SelectCandidate $select): RedirectResponse
    {
        $this->authorize('selectCandidate', [$requisition, $candidate]);

        $select->handle($requisition, $candidate, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => "{$candidate->name} dipilih."]);

        return back();
    }

    /**
     * "Batalkan pilihan": the direct superior takes their choice back (REC-PIP).
     */
    public function unselectCandidate(Request $request, Requisition $requisition, Candidate $candidate, UnselectCandidate $unselect): RedirectResponse
    {
        $this->authorize('unselectCandidate', [$requisition, $candidate]);

        $unselect->handle($requisition, $candidate, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => "Pilihan {$candidate->name} dibatalkan."]);

        return back();
    }

    /**
     * "Lanjut ke Penawaran ›" (REC-PIP), refused until the direct superior has chosen a candidate.
     */
    public function advanceToOffer(Request $request, Requisition $requisition, AdvanceToOffer $advance): RedirectResponse
    {
        $this->authorize('advanceToOffer', $requisition);

        try {
            $advance->handle($requisition, $request->user());
        } catch (TransitionBlocked $blocked) {
            throw ValidationException::withMessages(['advance' => $blocked->getMessage()]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => "{$requisition->reference()} lanjut ke tahap Penawaran."]);

        return back();
    }

    /**
     * "Tandai Selesai" (REC-FIL-001/003): the quota is filled, so the requisition moves to Terisi. The stage's guard refuses
     * while it is not. HR may tick that the other interviewed candidates are told by email.
     */
    public function markFilled(Request $request, Requisition $requisition, MarkRequisitionFilled $fill): RedirectResponse
    {
        $this->authorize('markFilled', $requisition);

        try {
            $emailed = $fill->handle($requisition, $request->user(), $request->boolean('notify_candidates'));
        } catch (TransitionBlocked $blocked) {
            throw ValidationException::withMessages(['filled' => $blocked->getMessage()]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => "{$requisition->reference()} terisi.".($emailed > 0 ? " {$emailed} kandidat dikabari lewat email." : '')]);

        return back();
    }

    /**
     * "Sudah diturunkan": HR marks a listing as taken down from its platform after the position is filled (REC-FIL-005), or
     * takes the mark back.
     */
    public function takeDownPosting(Requisition $requisition, RequisitionPosting $posting): RedirectResponse
    {
        $this->authorize('closeFilled', $requisition);

        $posting->forceFill(['taken_down_at' => $posting->taken_down_at === null ? now() : null])->save();

        return back();
    }

    /**
     * "Tautan Formulir": a candidate asks for the link to their form again. A new link is made and handed back once;
     * nothing is emailed and the invitation itself is left as it was.
     */
    public function renewFormLink(Requisition $requisition, Candidate $candidate, RenewFormLink $renew): RedirectResponse
    {
        $this->authorize('renewFormLink', [$requisition, $candidate]);

        $result = $renew->handle($requisition, $candidate, request()->user());

        Inertia::flash('formLink', [
            'candidateId' => $candidate->id,
            'name' => $candidate->name,
            'link' => $result['link'],
            'expiresOn' => InvitationMessage::dayLabel($result['expiresAt'], false),
        ]);

        return back();
    }

    /**
     * "Nilai": an interviewer's rating, recommendation and note about a candidate, after the interview (REC-INT). Saving
     * again replaces their own.
     */
    public function saveEvaluation(SaveEvaluationRequest $request, Requisition $requisition, Candidate $candidate, SaveEvaluation $save): RedirectResponse
    {
        $save->handle($candidate, $request->user(), $request->evaluationFields());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Penilaian disimpan.']);

        return back();
    }

    /**
     * A candidate's CV, from the private disk. A picture can be looked at in the page with `?inline=1`.
     */
    public function candidateCv(Request $request, Requisition $requisition, Candidate $candidate): StreamedResponse
    {
        $user = $request->user();

        // HR opens any CV; the direct superior only that of a candidate who was invited to interview.
        abort_unless(
            $user->can('viewCandidates', $requisition)
                || ($user->can('viewInterviewees', $requisition) && $candidate->inviteState() !== 'none')
                || $user->can('viewOfferedCandidate', [$requisition, $candidate]),
            403,
        );

        abort_unless($candidate->cv_path !== null && Storage::disk(Candidate::CV_DISK)->exists($candidate->cv_path), 404);

        return InlineImage::serve($request, Candidate::CV_DISK, $candidate->cv_path, (string) $candidate->cv_name);
    }

    /**
     * "Lanjut ke Interview ›" (REC-SCR), refused until somebody is shortlisted.
     */
    public function advanceToInterview(Request $request, Requisition $requisition, AdvanceToInterview $advance): RedirectResponse
    {
        $this->authorize('advanceToInterview', $requisition);

        try {
            $advance->handle($requisition, $request->user());
        } catch (TransitionBlocked $blocked) {
            throw ValidationException::withMessages(['advance' => $blocked->getMessage()]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => "{$requisition->reference()} lanjut ke tahap Interview."]);

        return back();
    }

    /**
     * "Batalkan lowongan": HR calls the requisition off with a reason (REC-PST-004).
     */
    public function cancel(Request $request, Requisition $requisition, CancelRequisition $cancel): RedirectResponse
    {
        $this->authorize('cancel', $requisition);

        $validated = $request->validate([
            'note' => ['required', 'string', 'max:2000'],
            'notify_candidates' => ['nullable', 'boolean'],
        ], ['note.required' => 'Tulis alasan pembatalan.']);

        $scheduled = $requisition->candidates()->inProcess()->where('interview_at', '>', now())->count();

        $emailed = $cancel->handle($requisition, $request->user(), $validated['note'], $request->boolean('notify_candidates'));

        // Candidates are outside the app: unless HR chose to email them, nothing tells them, and those without an email
        // are HR's to tell either way.
        $left = $scheduled - $emailed;
        $told = match (true) {
            $emailed > 0 => " {$emailed} kandidat dikabari lewat email".($left > 0 ? ", {$left} lainnya kabari sendiri." : '.'),
            $left > 0 => " {$left} kandidat sudah dijadwalkan interview, kabari mereka.",
            default => '',
        };

        Inertia::flash('toast', ['type' => 'success', 'message' => "{$requisition->reference()} dibatalkan.{$told}"]);

        return back();
    }

    /**
     * "Komentar": the thread between the requester, Management and HR (REC-APR-009).
     */
    public function comment(Request $request, Requisition $requisition, WorkflowEngine $engine, Notifier $notifier): RedirectResponse
    {
        $this->authorize('comment', $requisition);

        $validated = $request->validate(['body' => ['required', 'string', 'max:2000']], ['body.required' => 'Tulis komentar.']);

        $comment = $engine->comment($requisition->workflow, $request->user(), $validated['body']);
        $notifier->requisitionCommented($requisition, $comment, $request->user());
        app(WorkflowDiscussion::class)->markRead($requisition->workflow, $request->user());

        return back();
    }

    /**
     * The person opened the discussion: everything written so far counts as seen.
     */
    public function markCommentsRead(Request $request, Requisition $requisition): RedirectResponse
    {
        $this->authorize('view', $requisition);

        app(WorkflowDiscussion::class)->markRead($requisition->workflow, $request->user());

        return back();
    }

    /**
     * @return array{items: list<array<string, mixed>>, unread: int, unreadByStage: array<string, int>}
     */
    private function discussion(Requisition $requisition, User $viewer): array
    {
        return app(WorkflowDiscussion::class)->present(
            $requisition->workflow,
            $viewer,
            collect(RequisitionStage::cases())->mapWithKeys(fn (RequisitionStage $stage): array => [$stage->value => $stage->label()])->all(),
            fn (User $author): ?string => match (true) {
                $author->id === $requisition->created_by_user_id => 'PEMOHON',
                $author->isA(Role::Management) => 'MANAJEMEN',
                $author->isA(Role::HrAdmin) => 'HR',
                default => null,
            },
        );
    }

    /**
     * What the wizard offers: the departments this person may ask for, the Cabang and the people who can be the
     * direct superior in their companies, the contract types, the template's starting lines, and today's date
     * (in the company's timezone, which is what the start date is judged against).
     *
     * @return array<string, mixed>
     */
    private function formOptions(User $user): array
    {
        $departments = app(RequisitionDepartments::class)->for($user);
        $companies = $departments->pluck('company_id')->unique()->all();

        return [
            'today' => SaveRequisitionRequest::today()->toDateString(),
            'maxStart' => SaveRequisitionRequest::today()->addYears(SaveRequisitionRequest::MAX_YEARS_AHEAD)->toDateString(),
            'template' => app(RequisitionTemplate::class)->startingContent(),
            'employmentTypes' => array_map(fn (EmploymentType $type): array => [
                'value' => $type->value,
                'label' => $type->label(),
                'needsMonths' => Requisition::typeNeedsMonths($type),
            ], EmploymentType::cases()),
            'ownEmployeeId' => Employee::acrossCompanies()->where('user_id', $user->id)->value('id'),
            'managers' => Employee::acrossCompanies()->whereIn('company_id', $companies)->where('employment_status', EmploymentStatus::Active)
                ->orderBy('full_name')
                ->get(['id', 'full_name', 'employee_number', 'company_id'])
                ->map(fn (Employee $employee): array => [
                    'id' => $employee->id,
                    'name' => $employee->full_name,
                    'number' => $employee->employee_number,
                    'company_id' => $employee->company_id,
                ])->values(),
            // The positions of the departments this person may ask for: the list the position is chosen from.
            'positions' => Position::acrossCompanies()->whereIn('department_id', $departments->pluck('id'))
                ->orderBy('name')
                ->get(['id', 'name', 'department_id', 'company_id'])
                ->map(fn (Position $position): array => ['id' => $position->id, 'name' => $position->name, 'department_id' => $position->department_id, 'company_id' => $position->company_id])
                ->values(),
            'departments' => $departments->map(fn (Department $department): array => [
                'id' => $department->id,
                'name' => $department->name,
                'company_id' => $department->company_id,
            ])->values(),
            'branches' => Branch::query()->whereIn('company_id', $companies)->orderBy('name')
                ->get(['id', 'name', 'company_id'])
                ->map(fn (Branch $branch): array => ['id' => $branch->id, 'name' => $branch->name, 'company_id' => $branch->company_id])
                ->values(),
        ];
    }
}
