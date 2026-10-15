<?php

use App\Enums\Role;
use App\Models\CalendarEvent;
use App\Models\Company;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\RoleSeeder;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Holidays and events that repeat (ATT-WIZ-002): one ordinary row per occurrence, made together.
 */
beforeEach(function () {
    $this->seed(RoleSeeder::class);
    config(['attendance.device_timezone' => 'Asia/Jakarta']);
    $this->travelTo(CarbonImmutable::parse('2026-10-20 12:00', 'Asia/Jakarta'));

    $this->company = Company::factory()->create();
    $this->hr = User::factory()->create(['company_id' => $this->company->id]);
    $this->hr->assignRole(Role::HrAdmin->value);
});

/**
 * @param  array<string, mixed>  $overrides
 */
function addEvent(array $overrides = []): TestResponse
{
    return test()->actingAs(test()->hr)->post(route('calendar-events.store'), [
        'title' => 'Rapat rutin',
        'kind' => 'event',
        'starts_on' => '2026-11-02',
        'ends_on' => '2026-11-02',
        ...$overrides,
    ]);
}

/**
 * @return list<string>
 */
function startDates(): array
{
    return CalendarEvent::query()->orderBy('starts_on')->get()->map(fn (CalendarEvent $event): string => $event->starts_on->toDateString())->all();
}

it('adds a single event as before when nothing repeats', function () {
    addEvent()->assertRedirect();

    $event = CalendarEvent::firstOrFail();

    expect(CalendarEvent::count())->toBe(1)
        ->and($event->series_id)->toBeNull()
        ->and($event->repeat)->toBeNull();
});

it('makes one row per week until the end date, all in one series', function () {
    addEvent(['repeat' => 'weekly', 'repeat_until' => '2026-11-23'])->assertRedirect();

    expect(startDates())->toBe(['2026-11-02', '2026-11-09', '2026-11-16', '2026-11-23'])
        ->and(CalendarEvent::pluck('series_id')->unique()->count())->toBe(1)
        ->and(CalendarEvent::first()->repeat)->toBe('weekly');
});

it('keeps the length of a multi-day event on every occurrence', function () {
    addEvent(['title' => 'Cuti bersama', 'kind' => 'holiday', 'starts_on' => '2026-12-24', 'ends_on' => '2026-12-26', 'repeat' => 'yearly', 'repeat_until' => '2028-12-31'])->assertRedirect();

    expect(CalendarEvent::orderBy('starts_on')->get()->map(fn (CalendarEvent $event): string => $event->starts_on->toDateString().'→'.$event->ends_on->toDateString())->all())
        ->toBe(['2026-12-24→2026-12-26', '2027-12-24→2027-12-26', '2028-12-24→2028-12-26']);
});

it('lands a monthly event on the last day of a short month and goes back after it', function () {
    addEvent(['starts_on' => '2026-10-31', 'ends_on' => '2026-10-31', 'repeat' => 'monthly', 'repeat_until' => '2027-03-31'])->assertRedirect();

    expect(startDates())->toBe(['2026-10-31', '2026-11-30', '2026-12-31', '2027-01-31', '2027-02-28', '2027-03-31']);
});

it('needs an end date for a repeat, after the event, and a repeat that fits', function () {
    addEvent(['repeat' => 'weekly'])->assertSessionHasErrors(['repeat_until' => 'Isi sampai kapan berulang.']);
    addEvent(['repeat' => 'weekly', 'repeat_until' => '2026-11-02'])->assertSessionHasErrors('repeat_until');
    addEvent(['repeat' => 'daily', 'repeat_until' => '2026-12-31'])->assertSessionHasErrors('repeat');
    // Ten days cannot repeat every week: the occurrences would overlap.
    addEvent(['starts_on' => '2026-11-02', 'ends_on' => '2026-11-11', 'repeat' => 'weekly', 'repeat_until' => '2026-12-31'])->assertSessionHasErrors('repeat');

    expect(CalendarEvent::count())->toBe(0);
});

it('refuses a repeat that would make too many rows', function () {
    addEvent(['repeat' => 'weekly', 'repeat_until' => '2031-12-31'])->assertSessionHasErrors('repeat_until');

    expect(CalendarEvent::count())->toBe(0);
});

it('deletes one occurrence, or the whole series', function () {
    addEvent(['repeat' => 'weekly', 'repeat_until' => '2026-11-23'])->assertRedirect();
    $second = CalendarEvent::orderBy('starts_on')->skip(1)->firstOrFail();

    $this->actingAs($this->hr)->delete(route('calendar-events.destroy', $second))->assertRedirect();

    expect(startDates())->toBe(['2026-11-02', '2026-11-16', '2026-11-23']);

    $this->actingAs($this->hr)->delete(route('calendar-events.destroy', CalendarEvent::firstOrFail()), ['series' => true])->assertRedirect();

    expect(CalendarEvent::count())->toBe(0);
});

it('only deletes a series made by the same company\'s people', function () {
    addEvent(['repeat' => 'weekly', 'repeat_until' => '2026-11-09'])->assertRedirect();
    $other = User::factory()->create(['company_id' => Company::factory()->create()->id]);
    $other->assignRole(Role::HrAdmin->value);

    $this->actingAs($other)->delete(route('calendar-events.destroy', CalendarEvent::firstOrFail()), ['series' => true])->assertForbidden();

    expect(CalendarEvent::count())->toBe(2);
});

it('tells the calendar how an event repeats and how many there are', function () {
    addEvent(['repeat' => 'monthly', 'repeat_until' => '2026-12-02'])->assertRedirect();
    addEvent(['title' => 'Sekali', 'starts_on' => '2026-11-03', 'ends_on' => '2026-11-03'])->assertRedirect();

    $this->actingAs($this->hr)->get(route('attendance.calendar', ['view' => 'month', 'date' => '2026-11-15']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('events.0.title', 'Rapat rutin')
            ->where('events.0.repeat', 'Setiap bulan')
            ->where('events.0.seriesSize', 2)
            ->where('events.1.repeat', null)
            ->where('events.1.seriesSize', 1));
});
