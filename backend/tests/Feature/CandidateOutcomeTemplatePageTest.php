<?php

use App\Enums\Role;
use App\Filament\Pages\ManageCandidateOutcomeTemplate;
use App\Models\User;
use App\Support\Recruitment\CandidateOutcomeTemplate;
use Carbon\CarbonImmutable;
use Database\Seeders\RoleSeeder;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

/**
 * The admin page "Email hasil seleksi": who may open it, what it saves and refuses, and when the emails go out. What the
 * rejection and position-filled flows do with the wording is tested with them.
 */
beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

function outcomePage(Role $role = Role::HrAdmin)
{
    $user = User::factory()->create();
    $user->assignRole($role->value);
    test()->actingAs($user);

    return Livewire::test(ManageCandidateOutcomeTemplate::class);
}

it('keeps everyone but a parent-wide HR Admin out', function () {
    foreach ([[Role::HrAdmin, true], [Role::SystemAdmin, false], [Role::Management, false], [Role::Employee, false]] as [$role, $allowed]) {
        $user = User::factory()->create();
        $user->assignRole($role->value);
        $this->actingAs($user);

        expect(ManageCandidateOutcomeTemplate::canAccess())->toBe($allowed);
    }
});

it('opens with the built-in wording, previewed with an example candidate', function () {
    outcomePage()->assertFormSet(fn (array $state): bool => $state['rejection_body'] === CandidateOutcomeTemplate::REJECTION_BODY
        && $state['position_filled_body'] === CandidateOutcomeTemplate::POSITION_FILLED_BODY
        && str_contains($state['rejection_preview'], 'Halo Budi Santoso')
        && str_contains($state['position_filled_preview'], 'Staf Gudang')
        && ! str_contains($state['rejection_preview'], '{'));
});

it('saves the wording of both emails and audits the change', function () {
    outcomePage()
        ->fillForm([
            'rejection_subject' => 'Hasil seleksi {posisi}',
            'rejection_body' => "Yth. {nama},\n\nTerima kasih dari {perusahaan}.",
            'position_filled_subject' => 'Lowongan {posisi} terisi',
            'position_filled_body' => 'Halo {nama}, posisi sudah terisi.',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $template = app(CandidateOutcomeTemplate::class);

    expect($template->current(CandidateOutcomeTemplate::REJECTION))->toBe(['subject' => 'Hasil seleksi {posisi}', 'body' => "Yth. {nama},\n\nTerima kasih dari {perusahaan}."])
        ->and($template->current(CandidateOutcomeTemplate::POSITION_FILLED)['subject'])->toBe('Lowongan {posisi} terisi')
        ->and(Activity::query()->where('description', 'Outcome email template changed')->count())->toBe(1);
});

it('refuses a placeholder that does not exist, so the reason can never be one', function () {
    outcomePage()->fillForm(['rejection_body' => 'Alasan: {alasan}'])->call('save')->assertHasFormErrors(['rejection_body']);
    outcomePage()->fillForm(['position_filled_subject' => '{kantor}'])->call('save')->assertHasFormErrors(['position_filled_subject']);
    outcomePage()->fillForm(['rejection_subject' => ''])->call('save')->assertHasFormErrors(['rejection_subject' => 'required']);

    expect(app(CandidateOutcomeTemplate::class)->current(CandidateOutcomeTemplate::REJECTION)['body'])->toBe(CandidateOutcomeTemplate::REJECTION_BODY);
});

it('puts the built-in wording back in the form, saving nothing until Simpan', function () {
    app(CandidateOutcomeTemplate::class)->save(CandidateOutcomeTemplate::REJECTION, 'Lain', 'Lain');

    outcomePage()
        ->call('resetToDefault')
        ->assertFormSet(fn (array $state): bool => $state['rejection_body'] === CandidateOutcomeTemplate::REJECTION_BODY);

    expect(app(CandidateOutcomeTemplate::class)->current(CandidateOutcomeTemplate::REJECTION)['body'])->toBe('Lain');
});

it('sends the next day at 08:00 company time, whatever hour of the day it is', function () {
    config(['attendance.device_timezone' => 'Asia/Jakarta']);

    foreach (['2026-11-04 01:00', '2026-11-04 23:30'] as $moment) {
        $when = CandidateOutcomeTemplate::nextMorning(CarbonImmutable::parse($moment, 'Asia/Jakarta'));

        expect($when->format('Y-m-d H:i'))->toBe('2026-11-05 08:00')
            ->and($when->timezoneName)->toBe('Asia/Jakarta');
    }
});
