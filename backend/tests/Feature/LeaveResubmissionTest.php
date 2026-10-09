<?php

use App\Enums\Role;
use App\Models\CalendarEvent;
use App\Models\Company;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\User;
use App\Support\Leave\LeaveLimits;
use Database\Seeders\RoleSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Leave requests: supporting files, the maximum days HR sets per type, and correcting a rejected
 * request and sending it for approval again without losing what the approvers wrote.
 */
beforeEach(function () {
    $this->seed(RoleSeeder::class);
    Storage::fake('local');
    $this->company = Company::factory()->create();
    $this->hr = resubUser(Role::HrAdmin, $this->company);
    $this->staffUser = resubUser(Role::Employee, $this->company);
    $this->staff = Employee::factory()->forCompany($this->company)->create(['user_id' => $this->staffUser->id]);
});

function resubUser(Role $role, Company $company): User
{
    $user = User::factory()->create(['company_id' => $company->id]);
    $user->assignRole($role->value);

    return $user;
}

/**
 * @param  array<string, mixed>  $overrides
 */
function fileOwnLeave(User $as, Employee $employee, array $overrides = []): TestResponse
{
    return test()->actingAs($as)->post(route('leave-requests.store'), [
        'employee_id' => $employee->id,
        'leave_type' => 'annual',
        'start_on' => '2026-11-02',
        'end_on' => '2026-11-03',
        'reason' => 'Keperluan keluarga',
        ...$overrides,
    ]);
}

function latestLeave(): LeaveRequest
{
    return LeaveRequest::query()->latest('id')->firstOrFail();
}

function decideLeave(User $by, LeaveRequest $leave, string $decision, ?string $note = null): void
{
    test()->actingAs($by)->put(route('leave-requests.decision', $leave), ['decision' => $decision, 'note' => $note])->assertRedirect();
}

/**
 * @param  array<string, mixed>  $overrides
 */
function resubmitLeave(User $as, LeaveRequest $leave, array $overrides = []): TestResponse
{
    return test()->actingAs($as)->put(route('leave-requests.update', $leave), [
        'leave_type' => 'annual',
        'start_on' => '2026-11-02',
        'end_on' => '2026-11-02',
        'reason' => 'Keperluan keluarga',
        ...$overrides,
    ]);
}

// --- Maximum days per type ---

it('refuses a request longer than HR allows for its type', function () {
    app(LeaveLimits::class)->set('annual', 3);

    fileOwnLeave($this->staffUser, $this->staff, ['start_on' => '2026-11-02', 'end_on' => '2026-11-06'])
        ->assertSessionHasErrors(['end_on' => 'Cuti tahunan maksimal 3 hari kerja per pengajuan; pengajuan ini 5 hari kerja.']);
    expect(LeaveRequest::count())->toBe(0);

    fileOwnLeave($this->staffUser, $this->staff, ['start_on' => '2026-11-02', 'end_on' => '2026-11-04'])->assertSessionHasNoErrors();
    expect(LeaveRequest::count())->toBe(1);
});

it('leaves a type with no limit unlimited, and limits each type on its own', function () {
    app(LeaveLimits::class)->set('marriage', 2);

    fileOwnLeave($this->staffUser, $this->staff, ['leave_type' => 'annual', 'start_on' => '2026-11-02', 'end_on' => '2026-11-27'])->assertSessionHasNoErrors();
    fileOwnLeave($this->staffUser, $this->staff, ['leave_type' => 'marriage', 'start_on' => '2026-12-01', 'end_on' => '2026-12-05'])->assertSessionHasErrors('end_on');
});

it('offers the form each type\'s limit', function () {
    app(LeaveLimits::class)->set('sick', 14);

    $this->actingAs($this->staffUser)->get(route('leave-requests.create'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('leaveTypes', fn ($types) => collect($types)->firstWhere('value', 'sick')['maxDays'] === 14
                && collect($types)->firstWhere('value', 'annual')['maxDays'] === null));
});

// --- Attachments ---

it('keeps supporting files on the private disk and serves them only to people who may see the request', function () {
    fileOwnLeave($this->staffUser, $this->staff, [
        'attachments' => [UploadedFile::fake()->create('surat-dokter.pdf', 200, 'application/pdf'), UploadedFile::fake()->create('foto.jpg', 50, 'image/jpeg')],
    ])->assertSessionHasNoErrors();

    $leave = latestLeave()->load('attachments');
    $attachment = $leave->attachments->first();

    expect($leave->attachments)->toHaveCount(2)
        ->and($attachment->original_name)->toBe('surat-dokter.pdf');
    Storage::disk('local')->assertExists($attachment->path);

    $this->actingAs($this->staffUser)->get(route('leave-attachments.download', $attachment))->assertOk();
    $this->actingAs($this->hr)->get(route('leave-attachments.download', $attachment))->assertOk();

    $stranger = resubUser(Role::Employee, $this->company);
    Employee::factory()->forCompany($this->company)->create(['user_id' => $stranger->id]);
    $this->actingAs($stranger)->get(route('leave-attachments.download', $attachment))->assertForbidden();
});

it('lets a request go without any file', function () {
    fileOwnLeave($this->staffUser, $this->staff)->assertSessionHasNoErrors();

    expect(latestLeave()->attachments)->toHaveCount(0);
});

it('refuses files that are not a PDF or an image, too big, or too many', function () {
    fileOwnLeave($this->staffUser, $this->staff, ['attachments' => [UploadedFile::fake()->create('virus.exe', 10, 'application/x-msdownload')]])
        ->assertSessionHasErrors('attachments.0');
    fileOwnLeave($this->staffUser, $this->staff, ['attachments' => [UploadedFile::fake()->create('besar.pdf', 6000, 'application/pdf')]])
        ->assertSessionHasErrors('attachments.0');
    fileOwnLeave($this->staffUser, $this->staff, ['attachments' => array_map(fn (int $n) => UploadedFile::fake()->create("f{$n}.png", 5, 'image/png'), range(1, 6))])
        ->assertSessionHasErrors('attachments');

    expect(LeaveRequest::count())->toBe(0);
});

// --- Correcting and resubmitting ---

it('lets the requester correct a rejected request and sends it for approval again', function () {
    fileOwnLeave($this->staffUser, $this->staff)->assertSessionHasNoErrors();
    $leave = latestLeave();

    decideLeave($this->hr, $leave, 'reject', 'Bertabrakan dengan audit akhir tahun');

    resubmitLeave($this->staffUser, $leave, ['end_on' => '2026-11-02', 'note' => 'Saya persingkat jadi satu hari'])
        ->assertRedirect(route('leave-requests.index'));

    $leave->refresh();

    expect($leave->workflow->current_stage)->toBe('submitted')
        ->and($leave->workflow->completed_at)->toBeNull()
        ->and($leave->days)->toBe(1)
        ->and($leave->end_on->toDateString())->toBe('2026-11-02')
        ->and($leave->workflow->steps->first()->status->value)->toBe('pending')
        ->and($leave->workflow->steps->first()->note)->toBeNull();

    // HR sees it waiting again, and can decide it.
    $this->actingAs($this->hr)->get(route('leave-requests.index'))
        ->assertInertia(fn (Assert $page) => $page->where('waitingOnMe', 1)->where('requests.0.canDecide', true));
    decideLeave($this->hr, $leave, 'approve');
    expect($leave->refresh()->workflow->current_stage)->toBe('approved');
});

it('keeps what HR wrote, in order, across every round', function () {
    fileOwnLeave($this->staffUser, $this->staff)->assertSessionHasNoErrors();
    $leave = latestLeave();

    decideLeave($this->hr, $leave, 'reject', 'Pertama: tanggal bentrok');
    resubmitLeave($this->staffUser, $leave, ['note' => 'Sudah digeser'])->assertSessionHasNoErrors();
    decideLeave($this->hr, $leave, 'reject', 'Kedua: lampirkan bukti');
    resubmitLeave($this->staffUser, $leave, ['note' => 'Bukti terlampir'])->assertSessionHasNoErrors();

    $history = $this->actingAs($this->staffUser)->get(route('employees.show', $this->staff))
        ->viewData('page')['props']['requests'][0]['history'];

    expect(collect($history)->pluck('stage')->all())->toBe(['rejected', 'submitted', 'rejected', 'submitted'])
        ->and($history[0]['note'])->toBe('Pertama: tanggal bentrok')
        ->and($history[0]['author'])->toBe($this->hr->name)
        ->and($history[1]['note'])->toStartWith('Sudah digeser')
        ->and($history[2]['note'])->toBe('Kedua: lampirkan bukti')
        ->and($history[3]['stageLabel'])->toBe('Diajukan ulang');
});

it('records what changed with the new submission', function () {
    fileOwnLeave($this->staffUser, $this->staff)->assertSessionHasNoErrors();
    $leave = latestLeave();
    decideLeave($this->hr, $leave, 'reject', 'Terlalu panjang');

    resubmitLeave($this->staffUser, $leave, ['end_on' => '2026-11-02'])->assertSessionHasNoErrors();

    $comment = $leave->refresh()->workflow->comments->last();

    expect($comment->user_id)->toBe($this->staffUser->id)
        ->and($comment->body)->toContain('Diubah dari Cuti tahunan, 2026-11-02 – 2026-11-03 (2 hari)')
        ->and($comment->body)->toContain('2026-11-02 – 2026-11-02 (1 hari)');
});

it('applies the maximum days to a corrected request too', function () {
    app(LeaveLimits::class)->set('annual', 2);
    fileOwnLeave($this->staffUser, $this->staff)->assertSessionHasNoErrors();
    $leave = latestLeave();
    decideLeave($this->hr, $leave, 'reject', 'Tidak');

    resubmitLeave($this->staffUser, $leave, ['end_on' => '2026-11-06'])->assertSessionHasErrors('end_on');

    expect($leave->refresh()->workflow->current_stage)->toBe('rejected');
});

it('can add and remove files when correcting', function () {
    fileOwnLeave($this->staffUser, $this->staff, ['attachments' => [UploadedFile::fake()->create('lama.pdf', 10, 'application/pdf')]]);
    $leave = latestLeave();
    $old = $leave->attachments->first();
    decideLeave($this->hr, $leave, 'reject', 'Lampiran salah');

    resubmitLeave($this->staffUser, $leave, [
        'remove_attachments' => [$old->id],
        'attachments' => [UploadedFile::fake()->create('baru.pdf', 10, 'application/pdf')],
    ])->assertSessionHasNoErrors();

    $names = $leave->refresh()->attachments->pluck('original_name')->all();

    expect($names)->toBe(['baru.pdf']);
    Storage::disk('local')->assertMissing($old->path);
});

it('only lets a rejected request be corrected, and only by its requester or HR', function () {
    fileOwnLeave($this->staffUser, $this->staff)->assertSessionHasNoErrors();
    $leave = latestLeave();

    // Waiting: nobody changes it under the approver.
    resubmitLeave($this->staffUser, $leave)->assertForbidden();
    $this->actingAs($this->staffUser)->get(route('leave-requests.edit', $leave))->assertForbidden();

    decideLeave($this->hr, $leave, 'reject', 'Tidak');

    $stranger = resubUser(Role::Employee, $this->company);
    Employee::factory()->forCompany($this->company)->create(['user_id' => $stranger->id]);
    resubmitLeave($stranger, $leave)->assertForbidden();

    $this->actingAs($this->staffUser)->get(route('leave-requests.edit', $leave))->assertOk();
    resubmitLeave($this->hr, $leave)->assertSessionHasNoErrors();
    expect($leave->refresh()->workflow->current_stage)->toBe('submitted');

    // Approved: settled for good.
    decideLeave($this->hr, $leave, 'approve');
    resubmitLeave($this->staffUser, $leave)->assertForbidden();
});

it('tells the profile and the queue whether the viewer can correct a request', function () {
    fileOwnLeave($this->staffUser, $this->staff)->assertSessionHasNoErrors();
    $leave = latestLeave();
    decideLeave($this->hr, $leave, 'reject', 'Tidak');

    $this->actingAs($this->staffUser)->get(route('leave-requests.index'))
        ->assertInertia(fn (Assert $page) => $page->where('requests.0.canResubmit', true));

    $other = resubUser(Role::Employee, $this->company);
    Employee::factory()->forCompany($this->company)->create(['user_id' => $other->id]);
    $this->actingAs($this->hr)->get(route('employees.show', $this->staff))
        ->assertInertia(fn (Assert $page) => $page->where('requests.0.canResubmit', true));
});

it('opens a fresh chain with the current supervisor when sent again', function () {
    $supervisorUser = resubUser(Role::Supervisor, $this->company);
    $supervisor = Employee::factory()->forCompany($this->company)->create(['user_id' => $supervisorUser->id]);
    $this->staff->update(['manager_id' => $supervisor->id]);

    fileOwnLeave($this->staffUser, $this->staff)->assertSessionHasNoErrors();
    $leave = latestLeave();
    decideLeave($supervisorUser, $leave, 'reject', 'Tim kekurangan orang');

    resubmitLeave($this->staffUser, $leave)->assertSessionHasNoErrors();

    $steps = $leave->refresh()->workflow->steps;

    expect($steps)->toHaveCount(1)
        ->and($steps[0]->status->value)->toBe('pending')
        ->and($steps[0]->approver_user_id)->toBe($supervisorUser->id)
        ->and($leave->workflow->comments->first()->body)->toBe('Tim kekurangan orang');
});

// --- Working days (REQ-WIZ-005) ---

it('counts working days: weekends and holidays are not leave days', function () {
    // Friday 6 to Monday 9 November 2026 is two working days, not four.
    fileOwnLeave($this->staffUser, $this->staff, ['start_on' => '2026-11-06', 'end_on' => '2026-11-09'])->assertSessionHasNoErrors();
    expect(latestLeave()->days)->toBe(2);

    CalendarEvent::create(['title' => 'Libur', 'kind' => 'holiday', 'starts_on' => '2026-11-18', 'ends_on' => '2026-11-18']);
    fileOwnLeave($this->staffUser, $this->staff, ['start_on' => '2026-11-16', 'end_on' => '2026-11-20'])->assertSessionHasNoErrors();
    expect(latestLeave()->days)->toBe(4);
});

it('only takes off the events that apply to the employee', function () {
    $other = Employee::factory()->forCompany(Company::factory()->create())->create();
    CalendarEvent::create(['title' => 'Libur perusahaan lain', 'kind' => 'holiday', 'starts_on' => '2026-11-17', 'ends_on' => '2026-11-17', 'company_id' => $other->company_id]);

    fileOwnLeave($this->staffUser, $this->staff, ['start_on' => '2026-11-16', 'end_on' => '2026-11-20'])->assertSessionHasNoErrors();

    expect(latestLeave()->days)->toBe(5);
});

it('refuses a request that covers no working day', function () {
    fileOwnLeave($this->staffUser, $this->staff, ['start_on' => '2026-11-07', 'end_on' => '2026-11-08'])
        ->assertSessionHasErrors(['end_on' => 'Tanggal yang dipilih tidak mencakup hari kerja (akhir pekan atau hari libur).']);

    expect(LeaveRequest::count())->toBe(0);
});

it('measures the maximum in working days', function () {
    app(LeaveLimits::class)->set('annual', 3);

    // Seven calendar days, but only five working days: still over three.
    fileOwnLeave($this->staffUser, $this->staff, ['start_on' => '2026-11-02', 'end_on' => '2026-11-08'])->assertSessionHasErrors('end_on');
    // Friday to Tuesday is four calendar days, two working days: within three.
    fileOwnLeave($this->staffUser, $this->staff, ['start_on' => '2026-11-06', 'end_on' => '2026-11-10'])->assertSessionHasNoErrors();
    expect(latestLeave()->days)->toBe(3);
});

it('recounts a corrected request in working days', function () {
    fileOwnLeave($this->staffUser, $this->staff)->assertSessionHasNoErrors();
    $leave = latestLeave();
    decideLeave($this->hr, $leave, 'reject', 'Ubah tanggal');

    resubmitLeave($this->staffUser, $leave, ['start_on' => '2026-11-06', 'end_on' => '2026-11-09'])->assertSessionHasNoErrors();

    expect($leave->refresh()->days)->toBe(2);
});

it('recounts stored requests from the command, leaving approved ones unless asked', function () {
    fileOwnLeave($this->staffUser, $this->staff, ['start_on' => '2026-11-06', 'end_on' => '2026-11-09'])->assertSessionHasNoErrors();
    $waiting = latestLeave();
    fileOwnLeave($this->staffUser, $this->staff, ['start_on' => '2026-11-16', 'end_on' => '2026-11-20'])->assertSessionHasNoErrors();
    $approved = latestLeave();
    decideLeave($this->hr, $approved, 'approve');

    // The figures as they were counted before working days: calendar days.
    $waiting->forceFill(['days' => 4])->save();
    $approved->forceFill(['days' => 5])->save();
    CalendarEvent::create(['title' => 'Libur', 'kind' => 'holiday', 'starts_on' => '2026-11-18', 'ends_on' => '2026-11-18']);

    $this->artisan('leave:recount-days', ['--dry-run' => true])->assertSuccessful();
    expect($waiting->refresh()->days)->toBe(4);

    $this->artisan('leave:recount-days')->assertSuccessful();
    expect($waiting->refresh()->days)->toBe(2)->and($approved->refresh()->days)->toBe(5);

    $this->artisan('leave:recount-days', ['--all' => true])->assertSuccessful();
    expect($approved->refresh()->days)->toBe(4);
});
