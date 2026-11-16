<?php

namespace App\Filament\Pages;

use App\Enums\AdminNavigationGroup;
use App\Support\Recruitment\OnboardingEmailTemplate;
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
 * The words of the welcome email a new hire gets when HR marks their offer accepted: the first day, what to bring and how
 * to open an account. HR reviews the result for each hire and fills in the time and place before it goes.
 *
 * @property-read Schema $form
 */
class ManageOnboardingEmailTemplate extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedHandRaised;

    protected static ?string $navigationLabel = 'Email sambutan karyawan baru';

    protected static ?string $title = 'Email sambutan karyawan baru';

    protected static string|UnitEnum|null $navigationGroup = AdminNavigationGroup::Workflow;

    protected static ?int $navigationSort = 7;

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
        $this->form->fill($this->withPreview(app(OnboardingEmailTemplate::class)->current()));
    }

    public function defaultForm(Schema $schema): Schema
    {
        return $schema->statePath('data');
    }

    /**
     * The preview follows what is typed (when a field loses focus), filled with an example hire.
     */
    public function updatedData(): void
    {
        $this->data = $this->withPreview([
            'subject' => (string) ($this->data['subject'] ?? ''),
            'body' => (string) ($this->data['body'] ?? ''),
        ]);
    }

    /**
     * @param  array{subject: string, body: string}  $wording
     * @return array<string, string>
     */
    private function withPreview(array $wording): array
    {
        $values = OnboardingEmailTemplate::sampleValues();

        return [
            ...$wording,
            'preview' => 'Subjek: '.PlaceholderText::fill($wording['subject'], $values)."\n\n".PlaceholderText::fill($wording['body'], $values),
        ];
    }

    public function form(Schema $schema): Schema
    {
        $rule = fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
            $unknown = OnboardingEmailTemplate::unknownPlaceholders(is_string($value) ? $value : '');

            if ($unknown !== []) {
                $fail('Tidak dikenal: '.implode(', ', array_map(fn (string $name): string => '{'.$name.'}', $unknown)).'. Periksa daftar di atas formulir.');
            }
        };

        return $schema->components([
            Section::make('Email sambutan')
                ->schema([
                    TextInput::make('subject')
                        ->label('Subjek email')
                        ->required()
                        ->maxLength(200)
                        ->live(onBlur: true)
                        ->rule($rule),
                    Textarea::make('body')
                        ->label('Isi email')
                        ->required()
                        ->rows(20)
                        ->maxLength(6000)
                        ->live(onBlur: true)
                        ->helperText('Daftar barang yang dibawa ditulis langsung di sini. Gunakan {waktu_tempat} agar HR mengisi waktu dan tempat hari pertama.')
                        ->rule($rule),
                    Textarea::make('preview')
                        ->label('Pratinjau (dengan contoh karyawan baru)')
                        ->rows(20)
                        ->disabled()
                        ->dehydrated(false),
                ]),
        ]);
    }

    public function content(Schema $schema): Schema
    {
        $placeholders = implode("\n", array_map(
            fn (string $name, string $description): string => '{'.$name.'}  —  '.$description,
            array_keys(OnboardingEmailTemplate::PLACEHOLDERS),
            OnboardingEmailTemplate::PLACEHOLDERS,
        ));

        return $schema->components([
            Text::make('Email ini dikirim saat HR menandai penawaran diterima: HR melihat isinya, mengisi waktu dan tempat, lalu mengirim. Gaji, NIK dan nomor karyawan tidak pernah masuk ke email.'),
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
        $this->form->fill($this->withPreview(['subject' => OnboardingEmailTemplate::SUBJECT, 'body' => OnboardingEmailTemplate::BODY]));
    }

    public function save(): void
    {
        $data = $this->form->getState();
        $template = app(OnboardingEmailTemplate::class);
        $before = $template->current();

        $template->save((string) $data['subject'], (string) $data['body']);

        activity('settings')
            ->causedBy(auth()->user())
            ->withProperties(['before' => $before, 'after' => $template->current()])
            ->log('Onboarding email template changed');

        $this->form->fill($this->withPreview($template->current()));

        Notification::make()->title('Email sambutan disimpan.')->success()->send();
    }
}
