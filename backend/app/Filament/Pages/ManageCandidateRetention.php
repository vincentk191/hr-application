<?php

namespace App\Filament\Pages;

use App\Enums\AdminNavigationGroup;
use App\Support\Recruitment\CandidateRetention;
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
use UnitEnum;

/**
 * How long the record of a rejected candidate is kept before it is deleted, counted from the rejection. "Pulihkan" takes
 * the rejection back and so stops the clock. The deletion runs every night (`recruitment:purge-rejected-candidates`).
 *
 * @property-read Schema $form
 */
class ManageCandidateRetention extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTrash;

    protected static ?string $navigationLabel = 'Penyimpanan data pelamar';

    protected static ?string $title = 'Penyimpanan data pelamar';

    protected static string|UnitEnum|null $navigationGroup = AdminNavigationGroup::Workflow;

    protected static ?int $navigationSort = 8;

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->can('manage-recruitment-template') === true;
    }

    public function mount(): void
    {
        $this->form->fill(['months' => app(CandidateRetention::class)->months()]);
    }

    public function defaultForm(Schema $schema): Schema
    {
        return $schema->statePath('data');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('months')
                ->label('Simpan data pelamar yang ditolak selama (bulan)')
                ->helperText('Setelah itu seluruh datanya dihapus: profil, CV, penilaian, penawaran, surat dan email tentang pelamar itu.')
                ->numeric()
                ->integer()
                ->minValue(CandidateRetention::MIN_MONTHS)
                ->maxValue(CandidateRetention::MAX_MONTHS)
                ->required(),
        ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Text::make('Dihitung sejak pelamar ditolak. Pelamar yang sudah menjadi karyawan tidak pernah dihapus dari sini. Riwayat audit tetap menyimpan catatan singkat kejadiannya.'),
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
        $months = (int) $this->form->getState()['months'];
        $retention = app(CandidateRetention::class);
        $before = $retention->months();

        $retention->save($months);

        activity('settings')
            ->causedBy(auth()->user())
            ->withProperties(['before' => ['months' => $before], 'after' => ['months' => $months]])
            ->log('Candidate retention changed');

        Notification::make()->title('Penyimpanan data pelamar disimpan.')->success()->send();
    }
}
