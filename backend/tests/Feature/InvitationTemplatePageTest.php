<?php

use App\Enums\Role;
use App\Filament\Pages\ManageInvitationTemplate;
use App\Mail\CandidateInvitation;
use App\Models\Candidate;
use App\Models\Company;
use App\Models\Department;
use App\Models\Requisition;
use App\Models\User;
use App\Support\Recruitment\InvitationTemplate;
use App\Workflows\Definitions\RequisitionWorkflow;
use App\Workflows\WorkflowEngine;
use Carbon\CarbonImmutable;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

/**
 * The admin page "Template undangan" (REC-INT): who may open it, what it saves and refuses, that the invitations HR makes
 * afterwards use it, and that an invitation already sent keeps its words.
 */
beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->travelTo(CarbonImmutable::parse('2026-11-04 12:00', 'Asia/Jakarta'));
});

function invitationAdmin(Role $role = Role::HrAdmin, ?Company $company = null): User
{
    $user = User::factory()->create(['company_id' => $company?->id]);
    $user->assignRole($role->value);

    return $user;
}

function invitationPage()
{
    test()->actingAs(invitationAdmin());

    return Livewire::test(ManageInvitationTemplate::class);
}

it('lets a parent-wide HR Admin open the page and keeps everyone else out', function () {
    $this->actingAs(invitationAdmin());
    expect(ManageInvitationTemplate::canAccess())->toBeTrue();

    foreach ([[Role::HrAdmin, true], [Role::SystemAdmin, false], [Role::Management, false], [Role::Supervisor, false], [Role::Employee, false]] as [$role, $pinned]) {
        $this->actingAs(invitationAdmin($role, $pinned ? Company::factory()->create() : null));
        expect(ManageInvitationTemplate::canAccess())->toBeFalse();
    }
});

it('opens with the built-in invitation and a preview filled with an example candidate', function () {
    invitationPage()
        ->assertFormSet(fn (array $state): bool => $state['subject'] === InvitationTemplate::SUBJECT
            && $state['body'] === InvitationTemplate::BODY
            && str_contains($state['preview'], 'Halo Budi Santoso,')
            && str_contains($state['preview'], 'Selasa, 10 November 2026')
            && ! str_contains($state['preview'], '{'));
});

it('saves the subject and body, audits the change and shows it as the template', function () {
    invitationPage()
        ->fillForm(['subject' => 'Interview {posisi}', 'body' => "Hai {nama}, interview {hari_tanggal} pukul {jam}.\n\n{formulir}"])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(app(InvitationTemplate::class)->current())->toBe(['subject' => 'Interview {posisi}', 'body' => "Hai {nama}, interview {hari_tanggal} pukul {jam}.\n\n{formulir}"])
        ->and(Activity::query()->where('description', 'Invitation template changed')->count())->toBe(1);
});

it('refuses a placeholder that does not exist and a body without the ones an invitation needs', function () {
    invitationPage()
        ->fillForm(['body' => 'Hai {nama}, interview {hari_tanggal} {jam} {formulir} {nomor_hp}'])
        ->call('save')
        ->assertHasFormErrors(['body']);

    invitationPage()
        ->fillForm(['body' => 'Hai {nama}, sampai jumpa.'])
        ->call('save')
        ->assertHasFormErrors(['body']);

    invitationPage()
        ->fillForm(['subject' => 'Interview {kantor}'])
        ->call('save')
        ->assertHasFormErrors(['subject']);

    expect(app(InvitationTemplate::class)->current()['body'])->toBe(InvitationTemplate::BODY);
});

it('puts the built-in template back in the form, saving nothing until Simpan', function () {
    app(InvitationTemplate::class)->save('Lain', '{hari_tanggal} {jam} {formulir}');

    invitationPage()->call('resetToDefault')->assertFormSet(['body' => InvitationTemplate::BODY]);
});

it('collapses the gap an empty placeholder leaves', function () {
    $text = InvitationTemplate::render("A\n\n{formulir}\n\n{catatan}\n\nB", ['formulir' => '', 'catatan' => 'catatan']);

    expect($text)->toBe("A\n\ncatatan\n\nB");
});

// --- What the invitations use ---

function templatedInvitation(): array
{
    $company = Company::factory()->create(['name' => 'PT Maju Jaya']);
    $department = Department::factory()->create(['company_id' => $company->id]);
    $hr = invitationAdmin();
    $requisition = Requisition::factory()->create([
        'company_id' => $company->id,
        'department_id' => $department->id,
        'created_by_user_id' => $hr->id,
        'position_name' => 'Staf Gudang',
        'submitted_at' => now(),
    ]);
    app(WorkflowEngine::class)->start($requisition, new RequisitionWorkflow, [], $hr)->update(['current_stage' => 'interview']);
    $candidate = Candidate::factory()->for($requisition)->create(['name' => 'Budi', 'email' => 'budi@example.com']);

    return [$requisition->refresh(), $candidate, $hr];
}

it('builds the invitations from the template HR saved, and keeps what was sent', function () {
    Mail::fake();
    app(InvitationTemplate::class)->save('Undangan {posisi} - {perusahaan}', "Yth. {nama}\n\nJadwal: {hari_tanggal}, {jam}, {format}\n{tempat}\n\n{formulir}\n\n{catatan}");
    [$requisition, $candidate, $hr] = templatedInvitation();

    $this->actingAs($hr)->post(route('recruitment.candidates.invite', [$requisition, $candidate]), [
        'interview_at' => '2026-11-10T10:00', 'interview_mode' => 'online', 'interview_place' => 'https://meet.google.com/abc', 'note' => 'Bawa KTP.',
    ])->assertSessionDoesntHaveErrors();

    Mail::assertQueued(CandidateInvitation::class, fn (CandidateInvitation $mail): bool => $mail->subjectLine === 'Undangan Staf Gudang - PT Maju Jaya'
        && str_starts_with($mail->body, 'Yth. Budi')
        && str_contains($mail->body, 'Jadwal: Selasa, 10 November 2026, 10:00, Daring')
        && str_contains($mail->body, '/lamaran/')
        && str_contains($mail->body, 'Bawa KTP.'));

    $candidate->refresh();
    expect($candidate->invitation_subject)->toBe('Undangan Staf Gudang - PT Maju Jaya')
        ->and($candidate->invitation_emailed)->toBeTrue()
        ->and($candidate->invitation_message)->toContain('Jadwal: Selasa, 10 November 2026')
        ->and($candidate->invitation_message)->toContain('[tautan formulir pribadi')
        ->and($candidate->invitation_message)->not->toContain('/lamaran/');

    // A later change to the template does not rewrite the invitation that was sent.
    app(InvitationTemplate::class)->save('Baru', '{hari_tanggal} {jam} {formulir}');
    $this->actingAs($hr)->get(route('recruitment.show', $requisition))
        ->assertInertia(fn ($page) => $page
            ->where('requisition.shortlist.0.invitation.subject', 'Undangan Staf Gudang - PT Maju Jaya')
            ->where('requisition.shortlist.0.invitation.emailed', true)
            ->where('requisition.shortlist.0.invitation.sentOn', '2026-11-04')
            ->where('requisition.shortlist.0.invitation.message', fn (string $message): bool => str_contains($message, 'Yth. Budi'))
        );
});

it('has no invitation to show for a candidate who was never invited', function () {
    [$requisition, , $hr] = templatedInvitation();

    $this->actingAs($hr)->get(route('recruitment.show', $requisition))
        ->assertInertia(fn ($page) => $page->where('requisition.shortlist.0.invitation', null));
});
