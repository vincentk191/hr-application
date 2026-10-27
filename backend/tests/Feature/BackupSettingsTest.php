<?php

use App\Enums\Role;
use App\Filament\Pages\ManageBackups;
use App\Models\Company;
use App\Models\User;
use App\Support\AppSettings;
use App\Support\Backup\BackupSettings;
use App\Support\Backup\BackupStatus;
use Carbon\CarbonImmutable;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

/**
 * The admin page "Cadangan data" (README, "Backups"): who may open it, what it accepts, and what it shows of the last
 * run. docker/server/backup.sh reads the settings on the host and checks them again; this is the side that keeps odd
 * values from being saved in the first place.
 */
const BACKUP_KEY = 'age17zqkpcutysznaey2u252atnvtj5gjpyq3grfl3asc7kv5usw0akqd54q7d';

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->travelTo(CarbonImmutable::parse('2026-10-27 12:00', 'Asia/Jakarta'));
    config(['attendance.device_timezone' => 'Asia/Jakarta']);
});

function backupUser(Role $role, ?Company $company = null): User
{
    $user = User::factory()->create(['company_id' => $company?->id]);
    $user->assignRole($role->value);

    return $user;
}

/** A page opened by a System Admin who has just confirmed their password. */
function backupPage()
{
    test()->withSession(['auth.password_confirmed_at' => time()])->actingAs(backupUser(Role::SystemAdmin));

    return Livewire::test(ManageBackups::class);
}

function validBackupForm(array $overrides = []): array
{
    return [
        'enabled' => true,
        'age_recipient' => BACKUP_KEY,
        'remote' => 'b2:company-hr-backups/hr-app',
        'keep_days' => 7,
        'remote_keep_days' => 30,
        'ok_url' => 'https://hc-ping.com/0c5e1c5e-0000-4000-8000-000000000001',
        'fail_url' => 'https://hc-ping.com/0c5e1c5e-0000-4000-8000-000000000001/fail',
        ...$overrides,
    ];
}

// --- Who may open it ---

it('lets a parent-wide System Admin open the page', function () {
    $this->actingAs(backupUser(Role::SystemAdmin));

    expect(ManageBackups::canAccess())->toBeTrue();
});

it('keeps everyone else out', function (Role $role, bool $pinnedToCompany) {
    $this->actingAs(backupUser($role, $pinnedToCompany ? Company::factory()->create() : null));

    expect(ManageBackups::canAccess())->toBeFalse();
})->with([
    'HR admin' => [Role::HrAdmin, false],
    'Management' => [Role::Management, false],
    'Supervisor' => [Role::Supervisor, false],
    'Employee' => [Role::Employee, false],
    'a System Admin pinned to one company' => [Role::SystemAdmin, true],
]);

it('asks for the password before the page opens, and opens it once confirmed', function () {
    $this->actingAs(backupUser(Role::SystemAdmin));

    $this->get('/admin/manage-backups')->assertRedirect(route('password.confirm'));

    $this->withSession(['auth.password_confirmed_at' => time()])->get('/admin/manage-backups')->assertOk();
});

it('refuses an HR admin who has confirmed their password', function () {
    $this->actingAs(backupUser(Role::HrAdmin))
        ->withSession(['auth.password_confirmed_at' => time()])
        ->get('/admin/manage-backups')
        ->assertForbidden();
});

// --- Saving ---

it('saves the settings and records the change without the monitoring addresses', function () {
    backupPage()
        ->fillForm(validBackupForm())
        ->call('save')
        ->assertHasNoFormErrors();

    $settings = app(AppSettings::class);

    expect($settings->get(BackupSettings::RECIPIENT))->toBe(BACKUP_KEY)
        ->and($settings->get(BackupSettings::REMOTE))->toBe('b2:company-hr-backups/hr-app')
        ->and($settings->get(BackupSettings::KEEP_DAYS))->toBe(7)
        ->and($settings->get(BackupSettings::REMOTE_KEEP_DAYS))->toBe(30)
        ->and($settings->get(BackupSettings::ENABLED))->toBeTrue()
        ->and($settings->get(BackupSettings::OK_URL))->toStartWith('https://hc-ping.com/');

    $row = Activity::query()->where('log_name', 'settings')->where('description', 'Backup settings changed')->firstOrFail();

    expect($row->properties['after']['age_recipient'])->toBe(BACKUP_KEY)
        ->and($row->properties['after']['ok_url'])->toBe('(diatur)')
        ->and(json_encode($row->properties))->not->toContain('0c5e1c5e');
});

it('shows what was saved when the page is opened again', function () {
    backupPage()->fillForm(validBackupForm(['keep_days' => 14]))->call('save');

    backupPage()->assertSchemaStateSet(['age_recipient' => BACKUP_KEY, 'keep_days' => 14, 'remote_keep_days' => 30]);
});

it('treats a blank as "use the server\'s own value", and saves it as blank', function () {
    backupPage()
        ->fillForm(validBackupForm(['remote' => '', 'keep_days' => null, 'ok_url' => '', 'age_recipient' => '']))
        ->call('save')
        ->assertHasNoFormErrors();

    expect(app(BackupSettings::class)->values())
        ->remote->toBe('')
        ->keep_days->toBeNull()
        ->ok_url->toBe('')
        ->age_recipient->toBe('');
});

it('can pause the backups without losing the other settings', function () {
    backupPage()->fillForm(validBackupForm(['enabled' => false]))->call('save');

    expect(app(BackupSettings::class)->values())->enabled->toBeFalse()->remote->toBe('b2:company-hr-backups/hr-app');
});

// --- What it refuses ---

it('refuses a value the server could be tricked by or could not use', function (string $field, mixed $value) {
    backupPage()
        ->fillForm(validBackupForm([$field => $value]))
        ->call('save')
        ->assertHasFormErrors([$field]);

    expect(DB::table('app_settings')->where('key', 'like', 'backup.%')->exists())->toBeFalse();
})->with([
    'a key that is not an age key' => ['age_recipient', 'not-a-key'],
    'an age key one character short' => ['age_recipient', substr(BACKUP_KEY, 0, -1)],
    'an age key with a letter outside the alphabet' => ['age_recipient', 'age1'.str_repeat('b', 58)],
    'a destination that is an rclone option' => ['remote', '--password-command=touch /tmp/x'],
    'a destination that is an on-the-fly rclone connection' => ['remote', ':sftp,host=attacker.example,user=a:/'],
    'a destination that climbs out of its folder' => ['remote', 'b2:../../etc'],
    'a destination with no remote name' => ['remote', 'no-colon-here'],
    'a destination with a space' => ['remote', 'b2:my backups'],
    'a destination with a shell character' => ['remote', 'b2:a;rm'],
    'a monitoring address over plain http' => ['ok_url', 'http://hc-ping.com/abc'],
    'a monitoring address that is not an address' => ['fail_url', 'javascript:alert(1)'],
    'a monitoring address with a space' => ['ok_url', 'https://hc-ping.com/a b'],
    'zero days on the server' => ['keep_days', 0],
    'more than a year on the server' => ['keep_days', 366],
    'zero days off the server' => ['remote_keep_days', 0],
    'more than two years off the server' => ['remote_keep_days', 731],
]);

it('asks for the password again, and saves nothing, when the confirmation has expired', function () {
    $this->withSession(['auth.password_confirmed_at' => time() - 4 * 3600])->actingAs(backupUser(Role::SystemAdmin));

    Livewire::test(ManageBackups::class)
        ->fillForm(validBackupForm())
        ->call('save')
        ->assertRedirect(route('password.confirm'));

    expect(DB::table('app_settings')->where('key', 'like', 'backup.%')->exists())->toBeFalse();
});

// --- What it shows of the last run ---

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function backupReport(array $overrides = []): array
{
    return [
        'finished_at' => '2026-10-27T05:00:00Z',
        'last_success_at' => '2026-10-27T05:00:00Z',
        'ok' => true,
        'paused' => false,
        'remote_ok' => true,
        'message' => 'done',
        'folder' => '2026-10-27_0230',
        'bytes' => 217088,
        'effective' => ['recipient' => BACKUP_KEY, 'recipient_source' => 'page', 'remote' => 'b2:company-hr-backups/hr-app', 'remote_source' => 'file', 'keep_days' => 7, 'remote_keep_days' => 30],
        'remotes' => ['b2', 'gdrive'],
        ...$overrides,
    ];
}

it('describes each state of the last run', function (?array $report, string $state, string $heading) {
    $status = BackupStatus::from($report, CarbonImmutable::now(), 'Asia/Jakarta');

    expect($status->state)->toBe($state)->and($status->heading)->toBe($heading);
})->with([
    'nothing reported yet' => [null, BackupStatus::UNREPORTED, 'Belum ada laporan dari server'],
    'a report with no time' => [['ok' => true], BackupStatus::UNREPORTED, 'Belum ada laporan dari server'],
    'a good run with a copy off the server' => [backupReport(), BackupStatus::HEALTHY, 'Cadangan berjalan'],
    'a failed run' => [backupReport(['ok' => false, 'message' => 'database dump was empty']), BackupStatus::FAILED, 'Cadangan terakhir gagal'],
    'a good local backup whose copy off the server failed' => [backupReport(['remote_ok' => false]), BackupStatus::WARNING, 'Cadangan di server berhasil, tetapi salinan di luar server gagal'],
    'a good run that is more than 36 hours old' => [backupReport(['finished_at' => '2026-10-25T05:00:00Z', 'last_success_at' => '2026-10-25T05:00:00Z']), BackupStatus::WARNING, 'Tidak ada cadangan baru'],
    'a good run with no copy off the server' => [backupReport(['effective' => ['remote' => '', 'keep_days' => 7, 'remote_keep_days' => 30]]), BackupStatus::WARNING, 'Cadangan hanya ada di server ini'],
    'paused from the page' => [backupReport(['paused' => true]), BackupStatus::PAUSED, 'Cadangan otomatis dijeda'],
    'a last success time that is not a time' => [backupReport(['last_success_at' => 'yesterday-ish']), BackupStatus::WARNING, 'Tidak ada cadangan baru'],
]);

it('reads the reports backup.sh really writes', function () {
    // Captured from a run of docker/server/backup.sh against a throwaway stack: the contract between the two halves.
    $this->travelTo(CarbonImmutable::parse('2026-10-27 18:00', 'Asia/Jakarta'));
    $report = fn (string $file): array => json_decode((string) file_get_contents(base_path("tests/Fixtures/{$file}")), true);

    $ok = BackupStatus::from($report('backup-report-ok.json'), CarbonImmutable::now(), 'Asia/Jakarta');
    $failed = BackupStatus::from($report('backup-report-failed.json'), CarbonImmutable::now(), 'Asia/Jakarta');

    expect($ok->state)->toBe(BackupStatus::HEALTHY)
        ->and($ok->remotes)->toBe(['testlocal'])
        ->and(collect($ok->lines)->firstWhere('label', 'Folder')['value'])->toBe('2026-10-27_1044')
        ->and(collect($ok->lines)->firstWhere('label', 'Salinan di luar server')['value'])->toBe('testlocal:/work/remote-cap (dari backup.env di server)')
        ->and($failed->state)->toBe(BackupStatus::FAILED)
        ->and($failed->description)->toBe('failed while: archiving the VPN data')
        ->and(collect($failed->lines)->firstWhere('label', 'Cadangan berhasil terakhir')['value'])->toBe('27-10-2026 17:44');
});

it('shows the last report on the page, with the time in local time and the sizes readable', function () {
    app(AppSettings::class)->set(BackupSettings::STATUS, backupReport());

    backupPage()
        ->assertSee('Cadangan berjalan')
        ->assertSee('27-10-2026 12:00')
        ->assertSee('2026-10-27_0230')
        ->assertSee('b2, gdrive');
});

it('shows a failure prominently, with what the server said', function () {
    app(AppSettings::class)->set(BackupSettings::STATUS, backupReport(['ok' => false, 'message' => 'database dump was empty']));

    backupPage()->assertSee('Cadangan terakhir gagal')->assertSee('database dump was empty');
});

it('tells the person when nothing has ever been reported', function () {
    backupPage()->assertSee('Belum ada laporan dari server');
});

it('never writes the status itself: that is the server\'s report', function () {
    backupPage()->fillForm(validBackupForm())->call('save');

    expect(DB::table('app_settings')->where('key', BackupSettings::STATUS)->exists())->toBeFalse();
});
