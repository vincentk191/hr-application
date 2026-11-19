<?php

use App\Enums\Role;
use App\Filament\Pages\ManageBackups;
use App\Models\Company;
use App\Models\User;
use App\Support\Backup\BackupFiles;
use Database\Seeders\RoleSeeder;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

/**
 * The download button on the admin page "Cadangan data" (README, "Download from the admin page"): who may use it, that
 * what arrives is a valid archive holding exactly the backup's files, and that nothing but a finished backup is sent.
 * The folder is a temporary one standing in for the server's read-only mount.
 */
const BACKUP_RUN = '2026-11-18_023001';

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->backupDir = sys_get_temp_dir().'/hr-backups-'.bin2hex(random_bytes(6));
    mkdir($this->backupDir);
    config(['backup.directory' => $this->backupDir]);
});

afterEach(function () {
    foreach (glob($this->backupDir.'/*/*') ?: [] as $file) {
        @unlink($file);
    }
    foreach (glob($this->backupDir.'/*') ?: [] as $folder) {
        @rmdir($folder);
    }
    @rmdir($this->backupDir);
});

/**
 * @return array<string, string> the files written, name to contents
 */
function fakeBackup(string $directory, string $run = BACKUP_RUN, bool $finished = true): array
{
    $files = [
        'database.dump.age' => random_bytes(1000),
        'storage.tar.gz.age' => random_bytes(513),
        'wireguard.tar.gz.age' => random_bytes(40),
        'env.age' => random_bytes(512),
        'SHA256SUMS' => "abc  ./database.dump.age\n",
    ];

    if ($finished) {
        $files['MANIFEST.txt'] = "created:  2026-11-18T02:30:01\n";
    }

    mkdir("{$directory}/{$run}");

    foreach ($files as $name => $contents) {
        file_put_contents("{$directory}/{$run}/{$name}", $contents);
    }

    return $files;
}

function backupAdmin(Role $role = Role::SystemAdmin, ?Company $company = null): User
{
    $user = User::factory()->create(['company_id' => $company?->id]);
    $user->assignRole($role->value);

    return $user;
}

/** A request from a signed-in account that has just confirmed its password. */
function confirmed(User $user)
{
    return test()->actingAs($user)->withSession(['auth.password_confirmed_at' => time()]);
}

// --- Who may download ---

it('sends a System Admin the backup as one tar file, announced at its exact length', function () {
    fakeBackup($this->backupDir);

    $response = confirmed(backupAdmin())->get(route('backups.download', BACKUP_RUN))->assertOk();

    $body = $response->streamedContent();

    expect($response->headers->get('Content-Type'))->toBe('application/x-tar')
        ->and($response->headers->get('Content-Disposition'))->toContain('attachment')->toContain('hr-cadangan-'.BACKUP_RUN.'.tar')
        ->and((int) $response->headers->get('Content-Length'))->toBe(strlen($body))
        ->and($response->headers->get('Cache-Control'))->toContain('no-store');
});

it('keeps everyone else out', function (Role $role, bool $pinnedToCompany) {
    fakeBackup($this->backupDir);

    confirmed(backupAdmin($role, $pinnedToCompany ? Company::factory()->create() : null))
        ->get(route('backups.download', BACKUP_RUN))
        ->assertForbidden();
})->with([
    'HR admin' => [Role::HrAdmin, false],
    'Management' => [Role::Management, false],
    'Employee' => [Role::Employee, false],
    'a System Admin pinned to one company' => [Role::SystemAdmin, true],
]);

it('asks a signed-out visitor to sign in', function () {
    fakeBackup($this->backupDir);

    $this->get(route('backups.download', BACKUP_RUN))->assertRedirect(route('login'));
});

it('asks for the password again before sending anything', function () {
    fakeBackup($this->backupDir);

    $this->actingAs(backupAdmin())->get(route('backups.download', BACKUP_RUN))->assertRedirect(route('password.confirm'));
});

it('writes each download to the audit trail', function () {
    fakeBackup($this->backupDir);
    $admin = backupAdmin();

    confirmed($admin)->get(route('backups.download', BACKUP_RUN))->assertOk();

    $row = Activity::query()->where('description', 'Backup download started')->firstOrFail();

    expect($row->causer_id)->toBe($admin->id)
        ->and($row->properties['run'])->toBe(BACKUP_RUN);
});

// --- What arrives ---

it('is a valid archive that holds exactly the backup files, byte for byte', function () {
    $files = fakeBackup($this->backupDir);
    file_put_contents($this->backupDir.'/'.BACKUP_RUN.'/notes.txt', 'not part of a backup');
    file_put_contents($this->backupDir.'/'.BACKUP_RUN.'/.hidden', 'nor this');

    $body = confirmed(backupAdmin())->get(route('backups.download', BACKUP_RUN))->streamedContent();

    $tar = tempnam(sys_get_temp_dir(), 'hr-tar').'.tar';
    file_put_contents($tar, $body);

    $phar = new PharData($tar);

    foreach ($files as $name => $contents) {
        expect($phar[BACKUP_RUN.'/'.$name]->getContent())->toBe($contents);
    }

    expect(isset($phar[BACKUP_RUN.'/notes.txt']))->toBeFalse()
        ->and(isset($phar[BACKUP_RUN.'/.hidden']))->toBeFalse();

    // A strict reader as well: tar checks every header's checksum and the padding, which PharData is looser about.
    if (trim((string) shell_exec('command -v tar')) !== '') {
        exec('tar -tf '.escapeshellarg($tar).' 2>&1', $listing, $status);

        expect($status)->toBe(0)->and($listing)->toContain(BACKUP_RUN.'/env.age');
    }

    unlink($tar);
    unlink($this->backupDir.'/'.BACKUP_RUN.'/notes.txt');
    unlink($this->backupDir.'/'.BACKUP_RUN.'/.hidden');
});

// --- What is never sent ---

it('does not send a backup that is still being written', function () {
    fakeBackup($this->backupDir, BACKUP_RUN, finished: false);

    confirmed(backupAdmin())->get(route('backups.download', BACKUP_RUN))->assertNotFound();
});

it('refuses anything that is not a backup folder name', function (string $run) {
    fakeBackup($this->backupDir);
    file_put_contents($this->backupDir.'/secret.txt', 'outside');

    confirmed(backupAdmin())->get(route('backups.download', $run))->assertNotFound();

    unlink($this->backupDir.'/secret.txt');
})->with(['..', '.', '2026-11-18', 'database.dump.age', '2026-11-18_02300x', '%2e%2e']);

it('refuses a backup that does not exist', function () {
    confirmed(backupAdmin())->get(route('backups.download', '2026-01-01_000000'))->assertNotFound();
});

it('does not follow a link out of the backup folder', function () {
    $outside = sys_get_temp_dir().'/hr-outside-'.bin2hex(random_bytes(4));
    mkdir($outside);
    fakeBackup($outside, 'real');
    rename($outside.'/real', $outside.'/'.BACKUP_RUN);

    if (! @symlink($outside.'/'.BACKUP_RUN, $this->backupDir.'/'.BACKUP_RUN)) {
        test()->markTestSkipped('Symbolic links are not available here.');
    }

    confirmed(backupAdmin())->get(route('backups.download', BACKUP_RUN))->assertNotFound();

    unlink($this->backupDir.'/'.BACKUP_RUN);
    array_map('unlink', glob($outside.'/'.BACKUP_RUN.'/*'));
    rmdir($outside.'/'.BACKUP_RUN);
    rmdir($outside);
});

// --- The list on the page ---

it('lists the finished backups newest first and leaves out one still being written', function () {
    fakeBackup($this->backupDir, '2026-11-16_023001');
    fakeBackup($this->backupDir, '2026-11-18_023001');
    fakeBackup($this->backupDir, '2026-11-17_023001', finished: false);

    expect(array_column(app(BackupFiles::class)->runs(), 'name'))->toBe(['2026-11-18_023001', '2026-11-16_023001']);
});

it('offers a download link for each backup on the page', function () {
    fakeBackup($this->backupDir);

    $this->actingAs(backupAdmin())->withSession(['auth.password_confirmed_at' => time()]);

    Livewire::test(ManageBackups::class)->assertSee('18 Nov 2026, 02:30');
});

it('says what is missing instead of an empty list when the server is not set up for downloads', function () {
    config(['backup.directory' => $this->backupDir.'/not-there']);

    expect(app(BackupFiles::class)->state())->toBe(BackupFiles::NOT_MOUNTED)
        ->and(app(BackupFiles::class)->runs())->toBe([]);

    $this->actingAs(backupAdmin())->withSession(['auth.password_confirmed_at' => time()]);

    Livewire::test(ManageBackups::class)->assertSee('Unduhan belum aktif');
});

it('says so when there is no finished backup yet', function () {
    $this->actingAs(backupAdmin())->withSession(['auth.password_confirmed_at' => time()]);

    Livewire::test(ManageBackups::class)->assertSee('Belum ada cadangan yang selesai');
});
