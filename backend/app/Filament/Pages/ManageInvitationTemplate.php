<?php

namespace App\Filament\Pages;

use App\Enums\AdminNavigationGroup;
use App\Support\Recruitment\InvitationMessage;
use App\Support\Recruitment\InvitationTemplate;
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
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * The words of the interview invitation (REC-INT): the subject and the body, with `{placeholders}` filled in for each
 * candidate. The same body is the email and the text HR copies for WhatsApp. An invitation already sent keeps the
 * words it was sent with; a change here applies to the ones made after it.
 *
 * @property-read Schema $form
 */
class ManageInvitationTemplate extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedEnvelope;

    protected static ?string $navigationLabel = 'Template undangan';

    protected static ?string $title = 'Template undangan interview';

    protected static string|UnitEnum|null $navigationGroup = AdminNavigationGroup::Workflow;

    protected static ?int $navigationSort = 4;

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
        $this->form->fill($this->withPreview(app(InvitationTemplate::class)->current()));
    }

    public function defaultForm(Schema $schema): Schema
    {
        return $schema->statePath('data');
    }

    /**
     * The preview follows what is typed (when the field loses focus), filled with an example candidate.
     */
    public function updatedData(): void
    {
        $this->data = $this->withPreview([
            'subject' => (string) ($this->data['subject'] ?? ''),
            'body' => (string) ($this->data['body'] ?? ''),
        ]);
    }

    /**
     * @param  array{subject: string, body: string}  $template
     * @return array{subject: string, body: string, preview: string}
     */
    private function withPreview(array $template): array
    {
        $values = InvitationMessage::sampleValues();

        return [
            ...$template,
            'preview' => 'Subjek: '.InvitationTemplate::render($template['subject'], $values)."\n\n".InvitationTemplate::render($template['body'], $values),
        ];
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('subject')
                ->label('Subjek email')
                ->required()
                ->maxLength(200)
                ->live(onBlur: true)
                ->rule(fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                    $this->checkPlaceholders(is_string($value) ? $value : '', $fail);
                }),
            Textarea::make('body')
                ->label('Isi undangan')
                ->required()
                ->rows(18)
                ->maxLength(4000)
                ->live(onBlur: true)
                ->helperText('Dipakai sebagai email dan sebagai teks yang disalin untuk WhatsApp. Wajib memuat {hari_tanggal}, {jam} dan {formulir}.')
                ->rule(fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                    $body = is_string($value) ? $value : '';

                    $this->checkPlaceholders($body, $fail);

                    $missing = InvitationTemplate::missingRequired($body);

                    if ($missing !== []) {
                        $fail('Isi undangan harus memuat '.implode(', ', array_map(fn (string $name): string => '{'.$name.'}', $missing)).'.');
                    }
                }),
            Textarea::make('preview')
                ->label('Pratinjau (dengan contoh kandidat)')
                ->rows(18)
                ->disabled()
                ->dehydrated(false),
        ]);
    }

    /**
     * @param  Closure(string): mixed  $fail
     */
    private function checkPlaceholders(string $text, Closure $fail): void
    {
        $unknown = InvitationTemplate::unknownPlaceholders($text);

        if ($unknown !== []) {
            $fail('Tidak dikenal: '.implode(', ', array_map(fn (string $name): string => '{'.$name.'}', $unknown)).'. Periksa daftar di atas formulir.');
        }
    }

    public function content(Schema $schema): Schema
    {
        $list = implode("\n", array_map(
            fn (string $name, string $description): string => '{'.$name.'}  —  '.$description,
            array_keys(InvitationTemplate::PLACEHOLDERS),
            InvitationTemplate::PLACEHOLDERS,
        ));

        return $schema->components([
            Text::make('Kata dalam kurung kurawal diisi otomatis untuk setiap kandidat. Undangan yang sudah terkirim tidak berubah; perubahan berlaku untuk undangan berikutnya.'),
            Text::make($list)->extraAttributes(['style' => 'white-space: pre-line; font-family: monospace; font-size: 0.85rem']),
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
     * Puts the built-in subject and body in the form. Nothing is saved until "Simpan".
     */
    public function resetToDefault(): void
    {
        $this->form->fill($this->withPreview(['subject' => InvitationTemplate::SUBJECT, 'body' => InvitationTemplate::BODY]));
    }

    public function save(): void
    {
        $data = $this->form->getState();
        $template = app(InvitationTemplate::class);
        $before = $template->current();

        $template->save((string) $data['subject'], (string) $data['body']);

        activity('settings')
            ->causedBy(auth()->user())
            ->withProperties(['before' => $before, 'after' => $template->current()])
            ->log('Invitation template changed');

        $this->form->fill($this->withPreview($template->current()));

        Notification::make()->title('Template undangan disimpan.')->success()->send();
    }
}
