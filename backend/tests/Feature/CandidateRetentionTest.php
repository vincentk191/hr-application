<?php

use App\Enums\Role;
use App\Filament\Pages\ManageCandidateRetention;
use App\Models\Candidate;
use App\Models\CandidateOffer;
use App\Models\Company;
use App\Models\EmailMessage;
use App\Models\Employee;
use App\Models\Requisition;
use App\Models\User;
use App\Support\Recruitment\CandidateRetention;
use Carbon\CarbonImmutable;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

/**
 * How long a rejected candidate is kept: the admin setting (twelve months until changed) and the nightly deletion of the whole
 * record, which spares anyone who is not rejected, was rejected recently, or became an employee.
 *
 * "Now" is 2026-11-17 02:00 in Jakarta.
 */
beforeEach(function () {
    Storage::fake('local');
    $this->travelTo(CarbonImmutable::parse('2026-11-17 02:00', 'Asia/Jakarta'));
    $this->seed(RoleSeeder::class);

    $this->company = Company::factory()->create();
    $this->requisition = Requisition::factory()->create(['company_id' => $this->company->id]);
});

function rejectedCandidate(string $rejectedOn, string $name = 'Budi'): Candidate
{
    return Candidate::factory()->for(test()->requisition)->create([
        'name' => $name,
        'rejected_at' => $rejectedOn,
        'rejected_reason' => 'Kurang cocok',
    ]);
}

it('keeps twelve months until someone changes it, within one month to ten years', function () {
    $retention = app(CandidateRetention::class);

    expect($retention->months())->toBe(12);

    $retention->save(6);
    expect($retention->months())->toBe(6);

    $retention->save(0);
    expect($retention->months())->toBe(1);

    $retention->save(9999);
    expect($retention->months())->toBe(120);
});

it('deletes the whole record of a candidate rejected more than twelve months ago', function () {
    $old = rejectedCandidate('2025-11-16 10:00');
    Storage::disk('local')->put('candidate-cvs/old.pdf', 'cv');
    $old->forceFill(['cv_path' => 'candidate-cvs/old.pdf'])->save();

    $offer = CandidateOffer::factory()->create(['candidate_id' => $old->id, 'letter_pdf_path' => 'offer-letters/old.pdf', 'signed_letter_path' => 'offer-letters/old-signed.pdf']);
    Storage::disk('local')->put('offer-letters/old.pdf', 'letter');
    Storage::disk('local')->put('offer-letters/old-signed.pdf', 'signed');

    $aboutCandidate = EmailMessage::factory()->forCompany($this->company)->create(['related_type' => $old->getMorphClass(), 'related_id' => $old->id]);
    $aboutOffer = EmailMessage::factory()->forCompany($this->company)->create(['related_type' => $offer->getMorphClass(), 'related_id' => $offer->id]);
    $unrelated = EmailMessage::factory()->forCompany($this->company)->create();

    $this->artisan('recruitment:purge-rejected-candidates')->assertSuccessful();

    expect(Candidate::acrossCompanies()->whereKey($old->id)->exists())->toBeFalse()
        ->and(CandidateOffer::acrossCompanies()->whereKey($offer->id)->exists())->toBeFalse()
        ->and(EmailMessage::acrossCompanies()->whereKey($aboutCandidate->id)->exists())->toBeFalse()
        ->and(EmailMessage::acrossCompanies()->whereKey($aboutOffer->id)->exists())->toBeFalse()
        ->and(EmailMessage::acrossCompanies()->whereKey($unrelated->id)->exists())->toBeTrue()
        ->and(Activity::query()->where('description', 'Rejected candidates deleted after the retention period')->count())->toBe(1);

    Storage::disk('local')->assertMissing(['candidate-cvs/old.pdf', 'offer-letters/old.pdf', 'offer-letters/old-signed.pdf']);
});

it('leaves candidates who were rejected recently, are not rejected, or became an employee', function () {
    $recent = rejectedCandidate('2025-11-18 10:00', 'Recent');
    $active = Candidate::factory()->for($this->requisition)->create(['name' => 'Aktif']);
    $hired = rejectedCandidate('2025-01-01 10:00', 'Karyawan');
    CandidateOffer::factory()->create(['candidate_id' => $hired->id, 'employee_id' => Employee::factory()->forCompany($this->company)->create()->id]);

    $this->artisan('recruitment:purge-rejected-candidates')->assertSuccessful();

    expect(Candidate::acrossCompanies()->whereKey([$recent->id, $active->id, $hired->id])->count())->toBe(3);
});

it('follows the setting, and only counts when asked to delete', function () {
    $candidate = rejectedCandidate('2026-04-01 10:00');
    app(CandidateRetention::class)->save(6);

    $this->artisan('recruitment:purge-rejected-candidates --dry-run')->expectsOutputToContain('1 candidate(s)')->assertSuccessful();
    expect(Candidate::acrossCompanies()->whereKey($candidate->id)->exists())->toBeTrue();

    $this->artisan('recruitment:purge-rejected-candidates')->assertSuccessful();
    expect(Candidate::acrossCompanies()->whereKey($candidate->id)->exists())->toBeFalse();
});

function retentionPage(Role $role = Role::HrAdmin)
{
    $user = User::factory()->create();
    $user->assignRole($role->value);
    test()->actingAs($user);

    return Livewire::test(ManageCandidateRetention::class);
}

it('keeps everyone but a parent-wide HR Admin off the retention page', function () {
    foreach ([[Role::HrAdmin, true], [Role::SystemAdmin, false], [Role::Management, false], [Role::Employee, false]] as [$role, $allowed]) {
        $user = User::factory()->create();
        $user->assignRole($role->value);
        $this->actingAs($user);

        expect(ManageCandidateRetention::canAccess())->toBe($allowed);
    }
});

it('saves the months from the page and audits the change, refusing nonsense', function () {
    retentionPage()->assertFormSet(['months' => 12])
        ->fillForm(['months' => 18])->call('save')->assertHasNoFormErrors();

    expect(app(CandidateRetention::class)->months())->toBe(18)
        ->and(Activity::query()->where('description', 'Candidate retention changed')->count())->toBe(1);

    retentionPage()->fillForm(['months' => 0])->call('save')->assertHasFormErrors(['months']);
    retentionPage()->fillForm(['months' => 121])->call('save')->assertHasFormErrors(['months']);
    retentionPage()->fillForm(['months' => ''])->call('save')->assertHasFormErrors(['months' => 'required']);

    expect(app(CandidateRetention::class)->months())->toBe(18);
});
