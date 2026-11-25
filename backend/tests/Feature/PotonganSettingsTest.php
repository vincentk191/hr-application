<?php

use App\Enums\Role;
use App\Enums\Zona;
use App\Filament\Pages\ManagePotongan;
use App\Models\User;
use App\Support\AppSettings;
use App\Support\Attendance\PotonganCalculator;
use Database\Seeders\RoleSeeder;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

/**
 * The attendance deduction rates and exemptions set from the admin panel (Potongan absensi), and what they change in
 * the calculation.
 */
beforeEach(function () {
    $this->seed(RoleSeeder::class);
    config(['attendance.potongan' => [
        'rates' => ['hitam' => 200000, 'merah' => 100000, 'hijau' => 50000],
        'exempt_positions' => ['SUPERVISOR'],
        'exempt_periods' => ['SETAHUN'],
    ]]);

    $this->hr = User::factory()->create();
    $this->hr->assignRole(Role::HrAdmin->value);
});

it('falls back to the config defaults until someone saves', function () {
    $calculator = app(PotonganCalculator::class);

    expect($calculator->rateFor(Zona::Hitam))->toBe(200000)
        ->and($calculator->rateFor(Zona::Hijau))->toBe(50000)
        ->and($calculator->isExempt('Supervisor', null))->toBeTrue()
        ->and($calculator->isExempt(null, 'SETAHUN 2026'))->toBeTrue();
});

it('saves new rates from the admin page and uses them straight away, with the change on record', function () {
    $this->actingAs($this->hr);

    Livewire::test(ManagePotongan::class)
        ->assertSchemaStateSet(['rate_hitam' => 200000, 'rate_merah' => 100000, 'rate_hijau' => 50000])
        ->fillForm(['rate_hitam' => 250000, 'rate_merah' => 120000, 'rate_hijau' => 60000])
        ->call('save')
        ->assertHasNoFormErrors();

    $calculator = app(PotonganCalculator::class);

    // Two days of Alpa/Izin and two Telat: 2 x rate + the rate.
    expect($calculator->rateFor(Zona::Hitam))->toBe(250000)
        ->and($calculator->amount(Zona::Merah, 1, 1, 2))->toBe(120000 * 2 + 120000)
        ->and($calculator->amount(Zona::Hijau, 0, 0, 1))->toBe(30000);

    $log = Activity::where('log_name', 'settings')->first();

    expect($log->properties['before']['rate_hitam'])->toBe(200000)
        ->and($log->properties['after']['rate_hitam'])->toBe(250000)
        ->and($log->causer_id)->toBe($this->hr->id);
});

it('saves the exemptions, and clearing a list sticks over the config default', function () {
    $this->actingAs($this->hr);

    Livewire::test(ManagePotongan::class)
        ->assertSchemaStateSet(['exempt_positions' => ['SUPERVISOR'], 'exempt_periods' => ['SETAHUN']])
        ->fillForm(['exempt_positions' => ['Kepala Gudang', ' Kepala Gudang '], 'exempt_periods' => []])
        ->call('save')
        ->assertHasNoFormErrors();

    $calculator = app(PotonganCalculator::class);

    expect($calculator->isExempt('kepala gudang', null))->toBeTrue()
        ->and($calculator->isExempt('Asisten Kepala Gudang', null))->toBeFalse()
        ->and($calculator->isExempt('Supervisor', null))->toBeFalse()
        ->and($calculator->isExempt(null, 'SETAHUN 2026'))->toBeFalse()
        ->and(app(AppSettings::class)->get(PotonganCalculator::KEY_EXEMPT_POSITIONS))->toBe(['Kepala Gudang']);
});

it('rejects a missing or negative rate', function () {
    $this->actingAs($this->hr);

    Livewire::test(ManagePotongan::class)
        ->fillForm(['rate_hitam' => -1, 'rate_merah' => null])
        ->call('save')
        ->assertHasFormErrors(['rate_hitam', 'rate_merah']);

    expect(app(AppSettings::class)->get(PotonganCalculator::KEY_RATES))->toBeNull();
});

it('is open to those who manage attendance and no one else', function () {
    $employee = User::factory()->create();
    $employee->assignRole(Role::Employee->value);

    $this->actingAs($employee);
    expect(ManagePotongan::canAccess())->toBeFalse();

    $this->actingAs($this->hr);
    expect(ManagePotongan::canAccess())->toBeTrue();
});
