<?php

namespace App\Filament\Pages;

use App\Support\AppSettings;
use App\Support\Attendance\WorkSchedule;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;

/**
 * Working hours for the attendance calendar: when work starts, when it ends, and how many minutes
 * after the start still count as on time.
 *
 * Saturday and Sunday are fixed as non-working and are deliberately not a field here.
 *
 * @property-read Schema $form
 */
class ManageWorkingHours extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClock;

    protected static ?string $navigationLabel = 'Jam kerja';

    protected static ?string $title = 'Jam kerja';

    protected static ?int $navigationSort = 7;

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->can('manage-attendance-device') === true;
    }

    public function mount(): void
    {
        $schedule = WorkSchedule::current();

        $this->form->fill([
            'start' => $schedule->start,
            'end' => $schedule->end,
            'late_grace_minutes' => $schedule->lateGraceMinutes,
        ]);
    }

    public function defaultForm(Schema $schema): Schema
    {
        return $schema->statePath('data');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TimePicker::make('start')
                ->label('Jam masuk')
                ->seconds(false)
                ->required(),
            TimePicker::make('end')
                ->label('Jam pulang')
                ->seconds(false)
                ->required()
                ->after('start')
                ->validationMessages(['after' => 'Jam pulang harus setelah jam masuk.']),
            TextInput::make('late_grace_minutes')
                ->label('Toleransi keterlambatan (menit)')
                ->helperText('Scan masuk setelah jam masuk ditambah toleransi ini dihitung terlambat.')
                ->numeric()
                ->integer()
                ->minValue(0)
                ->maxValue(120)
                ->required(),
        ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Text::make('Berlaku untuk semua karyawan. Sabtu dan Minggu selalu dihitung hari libur dan tidak dapat diubah. Hari libur nasional belum diatur.'),
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
        $data = $this->form->getState();
        $settings = app(AppSettings::class);
        $before = WorkSchedule::current();

        $start = Carbon::parse($data['start'])->format('H:i');
        $end = Carbon::parse($data['end'])->format('H:i');
        $grace = (int) $data['late_grace_minutes'];

        $settings->set(WorkSchedule::KEY_START, $start);
        $settings->set(WorkSchedule::KEY_END, $end);
        $settings->set(WorkSchedule::KEY_GRACE, $grace);

        activity('settings')
            ->causedBy(auth()->user())
            ->withProperties([
                'before' => ['start' => $before->start, 'end' => $before->end, 'late_grace_minutes' => $before->lateGraceMinutes],
                'after' => ['start' => $start, 'end' => $end, 'late_grace_minutes' => $grace],
            ])
            ->log('Working hours changed');

        Notification::make()->title('Jam kerja disimpan.')->success()->send();
    }
}
