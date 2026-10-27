<?php

namespace App\Filament\Pages;

use App\Support\Backup\BackupSettings;
use App\Support\Backup\BackupStatus;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/**
 * How backups are made (README, "Backups"): the key they are encrypted to, where the copy off the server goes, how
 * long they are kept, and an optional monitoring address. Above the form, what the last run reported.
 *
 * The settings are read by docker/server/backup.sh on the host, which checks them again and ignores anything
 * odd (see {@see BackupSettings}). The page needs the password confirmed within the last few hours, on opening it
 * and again on saving, because a changed key or destination decides who can read the group's backups.
 *
 * @property-read Schema $form
 */
class ManageBackups extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArchiveBox;

    protected static ?string $navigationLabel = 'Cadangan data';

    protected static ?string $title = 'Cadangan data';

    protected static ?int $navigationSort = 8;

    /**
     * Fortify's own "confirm your password" step, so this page asks the same way the security settings do.
     *
     * @var string|array<int, string>
     */
    protected static string|array $routeMiddleware = ['password.confirm'];

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->can('manage-backup-settings') === true;
    }

    public function mount(): void
    {
        $this->form->fill(app(BackupSettings::class)->values());
    }

    public function defaultForm(Schema $schema): Schema
    {
        return $schema->statePath('data');
    }

    public function form(Schema $schema): Schema
    {
        $blank = 'Kosong = pakai nilai di backup.env di server.';

        return $schema->components([
            Toggle::make('enabled')
                ->label('Cadangan otomatis aktif')
                ->helperText('Dimatikan: jadwal di server tetap jalan tetapi tidak membuat cadangan, dan tidak mengirim tanda "berhasil" ke pemantauan.'),
            TextInput::make('age_recipient')
                ->label('Kunci publik enkripsi (age)')
                ->helperText('Dimulai dengan "age1". Buat pasangan kuncinya di komputer Anda dengan age-keygen; hanya kunci publik ini yang ditaruh di sini. Kunci privatnya jangan pernah ada di server: tanpanya cadangan tidak bisa dibuka. '.$blank)
                ->nullable()
                ->maxLength(100)
                ->autocomplete(false)
                ->regex(BackupSettings::RECIPIENT_PATTERN)
                ->validationMessages(['regex' => 'Bukan kunci publik age yang sah: harus dimulai "age1" dan 62 karakter.']),
            TextInput::make('remote')
                ->label('Tujuan salinan di luar server (rclone)')
                ->helperText('Contoh: b2:nama-bucket/hr-app. Remote-nya dibuat dulu di server dengan "rclone config"; kredensialnya sengaja tidak diatur dari sini. Pakai folder khusus, cadangan lama di sana dihapus otomatis. '.$blank)
                ->nullable()
                ->maxLength(200)
                ->autocomplete(false)
                ->regex(BackupSettings::REMOTE_PATTERN)
                ->validationMessages(['regex' => 'Harus berbentuk nama:folder, misalnya b2:nama-bucket/hr-app. Nama diawali huruf, angka atau garis bawah; folder tidak boleh memuat "..".']),
            TextInput::make('keep_days')
                ->label('Simpan di server (hari)')
                ->helperText($blank)
                ->numeric()
                ->integer()
                ->minValue(1)
                ->maxValue(365)
                ->nullable(),
            TextInput::make('remote_keep_days')
                ->label('Simpan di luar server (hari)')
                ->helperText($blank)
                ->numeric()
                ->integer()
                ->minValue(1)
                ->maxValue(730)
                ->nullable(),
            TextInput::make('ok_url')
                ->label('Alamat pemantauan: berhasil')
                ->helperText('Layanan seperti healthchecks.io menunggu panggilan ini tiap hari dan memberi tahu Anda bila tidak datang. Alamat ini memuat token rahasia. '.$blank)
                ->nullable()
                ->maxLength(500)
                ->autocomplete(false)
                ->regex(BackupSettings::URL_PATTERN)
                ->validationMessages(['regex' => 'Harus berupa alamat yang diawali https://.']),
            TextInput::make('fail_url')
                ->label('Alamat pemantauan: gagal')
                ->helperText($blank)
                ->nullable()
                ->maxLength(500)
                ->autocomplete(false)
                ->regex(BackupSettings::URL_PATTERN)
                ->validationMessages(['regex' => 'Harus berupa alamat yang diawali https://.']),
        ]);
    }

    public function content(Schema $schema): Schema
    {
        $status = app(BackupSettings::class)->status();

        return $schema->components([
            Callout::make($status->heading)
                ->description($status->description)
                ->status(match ($status->state) {
                    BackupStatus::HEALTHY => 'success',
                    BackupStatus::FAILED => 'danger',
                    BackupStatus::WARNING => 'warning',
                    default => 'info',
                }),
            Section::make('Laporan terakhir dari server')
                ->visible($status->lines !== [])
                ->collapsible()
                ->schema(array_map(
                    fn (array $line): Text => Text::make($line['label'].': '.$line['value']),
                    $status->lines,
                )),
            Text::make('Perubahan dipakai pada cadangan berikutnya (jadwalnya diatur di server, lihat README). Server memeriksa ulang setiap nilai dan mengabaikan yang tidak sah. Cadangan sekarang dijalankan dari server dengan "bash docker/server/backup.sh".'),
            Form::make([EmbeddedSchema::make('form')])
                ->id('form')
                ->livewireSubmitHandler('save')
                ->footer([
                    Actions::make([
                        Action::make('save')
                            ->label('Simpan')
                            ->submit('save')
                            ->keyBindings(['mod+s']),
                    ]),
                ]),
        ]);
    }

    public function save(): void
    {
        // The route asks for the password when the page opens; a form left open for hours is asked again here.
        if (! $this->passwordRecentlyConfirmed()) {
            session()->put('url.intended', static::getUrl());

            Notification::make()->title('Konfirmasi kata sandi Anda dulu.')->warning()->send();
            $this->redirect(route('password.confirm'));

            return;
        }

        $data = $this->form->getState();
        $settings = app(BackupSettings::class);
        $before = $settings->values();

        $settings->save($data);

        activity('settings')
            ->causedBy(auth()->user())
            ->withProperties([
                'before' => $settings->forAudit($before),
                'after' => $settings->forAudit($settings->values()),
            ])
            ->log('Backup settings changed');

        Notification::make()->title('Pengaturan cadangan disimpan.')->success()->send();
    }

    private function passwordRecentlyConfirmed(): bool
    {
        return time() - (int) session('auth.password_confirmed_at', 0) < (int) config('auth.password_timeout', 10800);
    }
}
