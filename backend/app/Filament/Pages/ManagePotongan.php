<?php

namespace App\Filament\Pages;

use App\Enums\AdminNavigationGroup;
use App\Enums\Zona;
use App\Support\AppSettings;
use App\Support\Attendance\PotonganCalculator;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * The attendance deduction (potongan): the daily rate of each Zona, and who is not deducted at all.
 *
 * What a day of Alpa, Izin or Telat costs is fixed in PotonganCalculator; the amounts and the exemptions are set here.
 *
 * @property-read Schema $form
 */
class ManagePotongan extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static ?string $navigationLabel = 'Potongan absensi';

    protected static ?string $title = 'Potongan absensi';

    protected static string|UnitEnum|null $navigationGroup = AdminNavigationGroup::Attendance;

    protected static ?int $navigationSort = 4;

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
        $this->form->fill($this->current());
    }

    public function defaultForm(Schema $schema): Schema
    {
        return $schema->statePath('data');
    }

    public function form(Schema $schema): Schema
    {
        $rates = array_map(
            fn (Zona $zona): TextInput => TextInput::make('rate_'.$zona->value)
                ->label('Tarif per hari, Zona '.$zona->label())
                ->prefix('Rp')
                ->numeric()
                ->integer()
                ->minValue(0)
                ->maxValue(100_000_000)
                ->required(),
            Zona::cases(),
        );

        return $schema->components([
            ...$rates,
            TagsInput::make('exempt_positions')
                ->label('Jabatan yang tidak dipotong')
                ->helperText('Harus sama persis dengan nama jabatan: "Supervisor" tidak mencakup "Asisten Supervisor".'),
            TagsInput::make('exempt_periods')
                ->label('Periode rekapitulasi yang tidak dipotong')
                ->helperText('Cukup memuat kata ini, mis. "SETAHUN" mencakup "SETAHUN 2026".'),
        ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Text::make('Alpa dan Izin dipotong sebesar tarif satu hari, Telat setengahnya, Sakit tidak dipotong. Rekapitulasi cabang yang sudah diimpor tidak dihitung ulang.'),
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
        $before = $this->current();
        $settings = app(AppSettings::class);

        $rates = [];

        foreach (Zona::cases() as $zona) {
            $rates[$zona->value] = (int) $data['rate_'.$zona->value];
        }

        $positions = $this->cleaned($data['exempt_positions'] ?? []);
        $periods = $this->cleaned($data['exempt_periods'] ?? []);

        $settings->set(PotonganCalculator::KEY_RATES, $rates);
        // Saved even when empty, so that clearing a list sticks instead of falling back to the config default again.
        $settings->set(PotonganCalculator::KEY_EXEMPT_POSITIONS, $positions);
        $settings->set(PotonganCalculator::KEY_EXEMPT_PERIODS, $periods);

        activity('settings')
            ->causedBy(auth()->user())
            ->withProperties([
                'before' => $before,
                'after' => [...$this->ratesToFields($rates), 'exempt_positions' => $positions, 'exempt_periods' => $periods],
            ])
            ->log('Potongan changed');

        Notification::make()->title('Potongan absensi disimpan.')->success()->send();
    }

    /**
     * The form's fields as they stand in the settings now.
     *
     * @return array<string, mixed>
     */
    private function current(): array
    {
        $settings = app(PotonganCalculator::class)->settings();

        return [
            ...$this->ratesToFields($settings['rates']),
            'exempt_positions' => $settings['exempt_positions'],
            'exempt_periods' => $settings['exempt_periods'],
        ];
    }

    /**
     * @param  array<string, int>  $rates
     * @return array<string, int>
     */
    private function ratesToFields(array $rates): array
    {
        $fields = [];

        foreach ($rates as $zona => $rate) {
            $fields['rate_'.$zona] = $rate;
        }

        return $fields;
    }

    /**
     * @return list<string>
     */
    private function cleaned(mixed $entries): array
    {
        return array_values(array_unique(array_filter(
            array_map(fn (mixed $entry): string => trim((string) $entry), is_array($entries) ? $entries : []),
            fn (string $entry): bool => $entry !== '',
        )));
    }
}
