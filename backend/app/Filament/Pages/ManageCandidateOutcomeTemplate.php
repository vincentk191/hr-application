<?php

namespace App\Filament\Pages;

use App\Enums\AdminNavigationGroup;
use App\Support\Recruitment\CandidateOutcomeTemplate;
use App\Support\Recruitment\PlaceholderText;
use BackedEnum;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * The words of the emails that tell a candidate how the selection ended: turned down after the interview, and the position
 * filled by someone else. HR can still edit the rejection email for one candidate before it is scheduled; the position-filled
 * email goes out as written here. Both go out the next morning.
 *
 * @property-read Schema $form
 */
class ManageCandidateOutcomeTemplate extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedEnvelope;

    protected static ?string $navigationLabel = 'Email hasil seleksi';

    protected static ?string $title = 'Email hasil seleksi';

    protected static string|UnitEnum|null $navigationGroup = AdminNavigationGroup::Workflow;

    protected static ?int $navigationSort = 6;

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
        $this->form->fill($this->withPreviews($this->saved()));
    }

    public function defaultForm(Schema $schema): Schema
    {
        return $schema->statePath('data');
    }

    /**
     * The wording as saved (or built in), one flat field per part.
     *
     * @return array<string, string>
     */
    private function saved(): array
    {
        $template = app(CandidateOutcomeTemplate::class);
        $fields = [];

        foreach (array_keys(CandidateOutcomeTemplate::KINDS) as $kind) {
            $wording = $template->current($kind);
            $fields["{$kind}_subject"] = $wording['subject'];
            $fields["{$kind}_body"] = $wording['body'];
        }

        return $fields;
    }

    /**
     * The previews follow what is typed (when a field loses focus), filled with an example candidate.
     */
    public function updatedData(): void
    {
        $fields = [];

        foreach (array_keys(CandidateOutcomeTemplate::KINDS) as $kind) {
            $fields["{$kind}_subject"] = (string) ($this->data["{$kind}_subject"] ?? '');
            $fields["{$kind}_body"] = (string) ($this->data["{$kind}_body"] ?? '');
        }

        $this->data = $this->withPreviews($fields);
    }

    /**
     * @param  array<string, string>  $fields
     * @return array<string, string>
     */
    private function withPreviews(array $fields): array
    {
        $values = CandidateOutcomeTemplate::sampleValues();

        foreach (array_keys(CandidateOutcomeTemplate::KINDS) as $kind) {
            $fields["{$kind}_preview"] = 'Subjek: '.PlaceholderText::fill($fields["{$kind}_subject"], $values)."\n\n".PlaceholderText::fill($fields["{$kind}_body"], $values);
        }

        return $fields;
    }

    public function form(Schema $schema): Schema
    {
        $sections = [];

        foreach (CandidateOutcomeTemplate::KINDS as $kind => $label) {
            $sections[] = Section::make($label)
                ->schema([
                    TextInput::make("{$kind}_subject")
                        ->label('Subjek email')
                        ->required()
                        ->maxLength(200)
                        ->live(onBlur: true)
                        ->rule($this->placeholderRule()),
                    Textarea::make("{$kind}_body")
                        ->label('Isi email')
                        ->required()
                        ->rows(10)
                        ->maxLength(4000)
                        ->live(onBlur: true)
                        ->rule($this->placeholderRule()),
                    Textarea::make("{$kind}_preview")
                        ->label('Pratinjau (dengan contoh kandidat)')
                        ->rows(10)
                        ->disabled()
                        ->dehydrated(false),
                ]);
        }

        return $schema->components($sections);
    }

    /**
     * @return Closure(): Closure
     */
    private function placeholderRule(): Closure
    {
        return fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
            $unknown = CandidateOutcomeTemplate::unknownPlaceholders(is_string($value) ? $value : '');

            if ($unknown !== []) {
                $fail('Tidak dikenal: '.implode(', ', array_map(fn (string $name): string => '{'.$name.'}', $unknown)).'. Periksa daftar di atas formulir.');
            }
        };
    }

    public function content(Schema $schema): Schema
    {
        $placeholders = implode("\n", array_map(
            fn (string $name, string $description): string => '{'.$name.'}  —  '.$description,
            array_keys(CandidateOutcomeTemplate::PLACEHOLDERS),
            CandidateOutcomeTemplate::PLACEHOLDERS,
        ));

        return $schema->components([
            Text::make('Kedua email dikirim keesokan paginya pukul '.sprintf('%02d.00', CandidateOutcomeTemplate::SEND_HOUR).', lewat email pengirim perusahaan. Alasan penolakan bersifat internal dan tidak pernah masuk ke email. Untuk penolakan, HR masih bisa mengubah teksnya per kandidat sebelum dijadwalkan.'),
            Text::make($placeholders)->extraAttributes(['style' => 'white-space: pre-line; font-family: monospace; font-size: 0.85rem']),
            Form::make([EmbeddedSchema::make('form')])
                ->id('form')
                ->livewireSubmitHandler('save')
                ->footer([
                    Actions::make([
                        Action::make('save')
                            ->label('Simpan')
                            ->submit('save')
                            ->keyBindings(['mod+s']),
                        Action::make('reset')
                            ->label('Kembalikan ke bawaan')
                            ->color('gray')
                            ->requiresConfirmation()
                            ->action(fn () => $this->resetToDefault()),
                    ]),
                ]),
        ]);
    }

    /**
     * Puts the built-in wording in the form. Nothing is saved until "Simpan".
     */
    public function resetToDefault(): void
    {
        $fields = [];

        foreach (array_keys(CandidateOutcomeTemplate::KINDS) as $kind) {
            $defaults = CandidateOutcomeTemplate::defaults($kind);
            $fields["{$kind}_subject"] = $defaults['subject'];
            $fields["{$kind}_body"] = $defaults['body'];
        }

        $this->form->fill($this->withPreviews($fields));
    }

    public function save(): void
    {
        $data = $this->form->getState();
        $template = app(CandidateOutcomeTemplate::class);
        $before = $this->saved();

        foreach (array_keys(CandidateOutcomeTemplate::KINDS) as $kind) {
            $template->save($kind, (string) $data["{$kind}_subject"], (string) $data["{$kind}_body"]);
        }

        activity('settings')
            ->causedBy(auth()->user())
            ->withProperties(['before' => $before, 'after' => $this->saved()])
            ->log('Outcome email template changed');

        $this->form->fill($this->withPreviews($this->saved()));

        Notification::make()->title('Email hasil seleksi disimpan.')->success()->send();
    }
}
