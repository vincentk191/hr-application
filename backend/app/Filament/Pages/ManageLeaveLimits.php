<?php

namespace App\Filament\Pages;

use App\Enums\LeaveType;
use App\Support\Leave\LeaveLimits;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/**
 * The longest a single leave request of each type may be. Left empty, a type has no limit.
 *
 * @property-read Schema $form
 */
class ManageLeaveLimits extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static ?string $navigationLabel = 'Batas cuti';

    protected static ?string $title = 'Batas hari cuti';

    protected static ?int $navigationSort = 8;

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->can('manage-leave-settings') === true;
    }

    public function mount(): void
    {
        $limits = app(LeaveLimits::class);

        $this->form->fill(collect(LeaveType::cases())
            ->mapWithKeys(fn (LeaveType $type): array => [$type->value => $limits->maxDays($type)])
            ->all());
    }

    public function defaultForm(Schema $schema): Schema
    {
        return $schema->statePath('data');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components(array_map(
            fn (LeaveType $type): TextInput => TextInput::make($type->value)
                ->label($type->label())
                ->helperText('Hari maksimal per pengajuan. Kosongkan jika tanpa batas.')
                ->numeric()
                ->integer()
                ->minValue(1)
                ->maxValue(365),
            LeaveType::cases(),
        ));
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Text::make('Pengajuan yang lebih panjang dari batas jenisnya ditolak saat diajukan atau diperbaiki. Hari dihitung kalender, termasuk akhir pekan. Pengajuan yang sudah ada tidak berubah.'),
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
        $limits = app(LeaveLimits::class);

        $before = [];
        $after = [];

        foreach (LeaveType::cases() as $type) {
            $new = filled($data[$type->value] ?? null) ? (int) $data[$type->value] : null;
            $before[$type->value] = $limits->maxDays($type);
            $after[$type->value] = $new;
            $limits->set($type, $new);
        }

        activity('settings')
            ->causedBy(auth()->user())
            ->withProperties(['before' => $before, 'after' => $after])
            ->log('Leave limits changed');

        Notification::make()->title('Batas cuti disimpan.')->success()->send();
    }
}
