<?php

namespace App\Filament\Pages;

use App\Enums\AdminNavigationGroup;
use App\Models\Company;
use App\Support\Recruitment\OfferLetterTemplate;
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
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * The words of the offer letter and of the email that goes with it (REC-OFR), and who signs for each company. The terms
 * (position, start date, salary and the rest) are printed from the offer whatever is written here. A letter already marked
 * sent keeps the words it was sent with; a change here applies to the letters made after it.
 *
 * @property-read Schema $form
 */
class ManageOfferLetterTemplate extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static ?string $navigationLabel = 'Template surat penawaran';

    protected static ?string $title = 'Template surat penawaran';

    protected static string|UnitEnum|null $navigationGroup = AdminNavigationGroup::Workflow;

    protected static ?int $navigationSort = 5;

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
        $this->form->fill($this->withPreviews(app(OfferLetterTemplate::class)->current()));
    }

    public function defaultForm(Schema $schema): Schema
    {
        return $schema->statePath('data');
    }

    /**
     * The previews follow what is typed (when a field loses focus), filled with an example offer.
     */
    public function updatedData(): void
    {
        $this->data = $this->withPreviews([
            'title' => (string) ($this->data['title'] ?? ''),
            'subject' => (string) ($this->data['subject'] ?? ''),
            'opening' => (string) ($this->data['opening'] ?? ''),
            'closing' => (string) ($this->data['closing'] ?? ''),
            'email_subject' => (string) ($this->data['email_subject'] ?? ''),
            'email_body' => (string) ($this->data['email_body'] ?? ''),
            'signers' => is_array($this->data['signers'] ?? null) ? $this->data['signers'] : [],
        ]);
    }

    /**
     * @param  array<string, mixed>  $template
     * @return array<string, mixed>
     */
    private function withPreviews(array $template): array
    {
        $values = OfferLetterTemplate::sampleValues();

        $letter = implode("\n\n", [
            mb_strtoupper(PlaceholderText::fill((string) $template['title'], $values)),
            'Perihal: '.PlaceholderText::fill((string) $template['subject'], $values),
            "Kepada Yth.\n".$values['nama'],
            PlaceholderText::fill((string) $template['opening'], $values),
            '[Ketentuan: posisi, departemen, cabang, atasan langsung, status kerja, tanggal mulai, masa percobaan dan gaji. Selalu dicetak dari penawaran.]',
            PlaceholderText::fill((string) $template['closing'], $values),
            "Hormat kami,\n".$values['perusahaan']."\n\n[Penandatangan sesuai perusahaan]",
        ]);

        $email = 'Subjek: '.PlaceholderText::fill((string) $template['email_subject'], $values)."\n\n"
            .PlaceholderText::fill((string) $template['email_body'], $values);

        return [...$template, 'letter_preview' => $letter, 'email_preview' => $email];
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Surat penawaran')
                ->schema([
                    TextInput::make('title')
                        ->label('Judul surat')
                        ->required()
                        ->maxLength(100)
                        ->live(onBlur: true)
                        ->rule($this->letterPlaceholderRule()),
                    TextInput::make('subject')
                        ->label('Perihal')
                        ->required()
                        ->maxLength(200)
                        ->live(onBlur: true)
                        ->rule($this->letterPlaceholderRule()),
                    Textarea::make('opening')
                        ->label('Pembuka (sebelum ketentuan)')
                        ->required()
                        ->rows(7)
                        ->maxLength(2000)
                        ->live(onBlur: true)
                        ->helperText('Pisahkan paragraf dengan satu baris kosong.')
                        ->rule($this->letterPlaceholderRule()),
                    Textarea::make('closing')
                        ->label('Penutup (setelah ketentuan)')
                        ->required()
                        ->rows(7)
                        ->maxLength(2000)
                        ->live(onBlur: true)
                        ->helperText('Pisahkan paragraf dengan satu baris kosong.')
                        ->rule($this->letterPlaceholderRule()),
                    Textarea::make('letter_preview')
                        ->label('Pratinjau surat (dengan contoh penawaran)')
                        ->rows(20)
                        ->disabled()
                        ->dehydrated(false),
                ]),
            Section::make('Penandatangan')
                ->schema($this->signerFields()),
            Section::make('Email ke kandidat')
                ->schema([
                    TextInput::make('email_subject')
                        ->label('Subjek email')
                        ->required()
                        ->maxLength(200)
                        ->live(onBlur: true)
                        ->rule($this->emailPlaceholderRule()),
                    Textarea::make('email_body')
                        ->label('Isi email')
                        ->required()
                        ->rows(14)
                        ->maxLength(4000)
                        ->live(onBlur: true)
                        ->helperText('Wajib memuat {ketentuan}.')
                        ->rule(fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                            $body = is_string($value) ? $value : '';

                            $this->failOnUnknown(OfferLetterTemplate::unknownEmailPlaceholders($body), $fail);

                            $missing = OfferLetterTemplate::missingEmailRequired($body);

                            if ($missing !== []) {
                                $fail('Isi email harus memuat '.implode(', ', array_map(fn (string $name): string => '{'.$name.'}', $missing)).'.');
                            }
                        }),
                    Textarea::make('email_preview')
                        ->label('Pratinjau email (dengan contoh penawaran)')
                        ->rows(14)
                        ->disabled()
                        ->dehydrated(false),
                ]),
        ]);
    }

    /**
     * A name and a title for each active company.
     *
     * @return list<Fieldset>
     */
    private function signerFields(): array
    {
        $fields = [];

        foreach (Company::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']) as $company) {
            $fields[] = Fieldset::make($company->name)
                ->columns(2)
                ->schema([
                    TextInput::make("signers.{$company->id}.name")
                        ->label('Nama penandatangan')
                        ->maxLength(100),
                    TextInput::make("signers.{$company->id}.title")
                        ->label('Jabatan')
                        ->placeholder(OfferLetterTemplate::SIGNER_TITLE)
                        ->maxLength(100),
                ]);
        }

        return $fields;
    }

    /**
     * @return Closure(): Closure
     */
    private function letterPlaceholderRule(): Closure
    {
        return fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
            $this->failOnUnknown(OfferLetterTemplate::unknownLetterPlaceholders(is_string($value) ? $value : ''), $fail);
        };
    }

    /**
     * @return Closure(): Closure
     */
    private function emailPlaceholderRule(): Closure
    {
        return fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
            $this->failOnUnknown(OfferLetterTemplate::unknownEmailPlaceholders(is_string($value) ? $value : ''), $fail);
        };
    }

    /**
     * @param  list<string>  $unknown
     * @param  Closure(string): mixed  $fail
     */
    private function failOnUnknown(array $unknown, Closure $fail): void
    {
        if ($unknown !== []) {
            $fail('Tidak dikenal: '.implode(', ', array_map(fn (string $name): string => '{'.$name.'}', $unknown)).'. Periksa daftar di atas formulir.');
        }
    }

    public function content(Schema $schema): Schema
    {
        $describe = fn (array $placeholders): string => implode("\n", array_map(
            fn (string $name, string $description): string => '{'.$name.'}  —  '.$description,
            array_keys($placeholders),
            $placeholders,
        ));

        return $schema->components([
            Text::make('Kata dalam kurung kurawal diisi otomatis untuk setiap penawaran. Ketentuan (posisi, tanggal mulai, gaji dan lainnya) selalu dicetak dari penawaran. Surat yang sudah ditandai terkirim tidak berubah; perubahan berlaku untuk surat berikutnya.'),
            Text::make($describe(OfferLetterTemplate::PLACEHOLDERS))->extraAttributes(['style' => 'white-space: pre-line; font-family: monospace; font-size: 0.85rem']),
            Text::make('Khusus email:'),
            Text::make($describe(OfferLetterTemplate::EMAIL_PLACEHOLDERS))->extraAttributes(['style' => 'white-space: pre-line; font-family: monospace; font-size: 0.85rem']),
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
                            ->modalDescription('Kata-kata surat dan email dikembalikan ke bawaan; penandatangan tidak berubah.')
                            ->action(fn () => $this->resetToDefault()),
                    ]),
                ]),
        ]);
    }

    /**
     * Puts the built-in wording in the form, keeping the signers. Nothing is saved until "Simpan".
     */
    public function resetToDefault(): void
    {
        $this->form->fill($this->withPreviews([
            'title' => OfferLetterTemplate::TITLE,
            'subject' => OfferLetterTemplate::SUBJECT,
            'opening' => OfferLetterTemplate::OPENING,
            'closing' => OfferLetterTemplate::CLOSING,
            'email_subject' => OfferLetterTemplate::EMAIL_SUBJECT,
            'email_body' => OfferLetterTemplate::EMAIL_BODY,
            'signers' => is_array($this->data['signers'] ?? null) ? $this->data['signers'] : [],
        ]));
    }

    public function save(): void
    {
        $data = $this->form->getState();
        $template = app(OfferLetterTemplate::class);
        $before = $template->current();

        $template->save($data);

        activity('settings')
            ->causedBy(auth()->user())
            ->withProperties(['before' => $before, 'after' => $template->current()])
            ->log('Offer letter template changed');

        $this->form->fill($this->withPreviews($template->current()));

        Notification::make()->title('Template surat penawaran disimpan.')->success()->send();
    }
}
