<?php

namespace App\Filament\Pages;

use App\Support\Recruitment\RequisitionTemplate;
use BackedEnum;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/**
 * The base template every requisition starts from (REC-TPL-001): the lines under Benefit & Fasilitas and under each
 * fixed subtitle of the candidate checklist. A requisition copies them when it is started and keeps its own after
 * that, so changing them here never rewrites a request already under way. The sections themselves are fixed.
 *
 * @property-read Schema $form
 */
class ManageRecruitmentTemplate extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static ?string $navigationLabel = 'Template rekrutmen';

    protected static ?string $title = 'Template rekrutmen';

    protected static ?int $navigationSort = 8;

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    /** The fields of the form: key => label, in the order the requisition shows them. */
    private const FIELDS = [
        'benefits' => 'Benefit & Fasilitas',
        'education' => 'Kualifikasi · Pendidikan',
        'experience' => 'Kualifikasi · Pengalaman',
        'hard' => 'Skills · Hard Skills',
        'soft' => 'Skills · Soft Skills',
        'bonus' => 'Nilai Plus',
    ];

    public static function canAccess(): bool
    {
        return auth()->user()?->can('manage-recruitment-template') === true;
    }

    public function mount(): void
    {
        $current = app(RequisitionTemplate::class)->current();

        $this->form->fill(array_map(fn (array $lines): string => RequisitionTemplate::toText($lines), $current));
    }

    public function defaultForm(Schema $schema): Schema
    {
        return $schema->statePath('data');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components(array_map(
            fn (string $key, string $label): Textarea => Textarea::make($key)
                ->label($label)
                ->helperText('Satu baris satu poin. Maksimal '.RequisitionTemplate::MAX_LINES.' baris, '.RequisitionTemplate::MAX_LINE_LENGTH.' karakter per baris.')
                ->rows(5)
                ->nullable()
                // Filament runs this outer closure itself and takes the rule it returns.
                ->rule(fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                    $lines = RequisitionTemplate::fromText(is_string($value) ? $value : '');

                    if (count($lines) > RequisitionTemplate::MAX_LINES) {
                        $fail('Maksimal '.RequisitionTemplate::MAX_LINES.' baris.');

                        return;
                    }

                    foreach ($lines as $line) {
                        if (mb_strlen($line) > RequisitionTemplate::MAX_LINE_LENGTH) {
                            $fail('Satu baris maksimal '.RequisitionTemplate::MAX_LINE_LENGTH.' karakter.');

                            return;
                        }
                    }
                }),
            array_keys(self::FIELDS),
            self::FIELDS,
        ));
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Text::make('Bagian-bagian lowongan tetap: Tentang Posisi, Tanggung Jawab Utama, Benefit & Fasilitas, lalu checklist kandidat (Kualifikasi, Skills, Nilai Plus). Di sini HR menetapkan baris awal yang sama untuk semua lowongan baru. Permintaan yang sudah dibuat tidak berubah, dan peminta tetap bisa menambah atau menghapus baris di permintaannya.'),
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
        $template = app(RequisitionTemplate::class);
        $before = $template->current();

        $template->save(array_map(fn (mixed $text): array => RequisitionTemplate::fromText(is_string($text) ? $text : ''), $data));

        activity('settings')
            ->causedBy(auth()->user())
            ->withProperties(['before' => $before, 'after' => $template->current()])
            ->log('Recruitment template changed');

        Notification::make()->title('Template rekrutmen disimpan.')->success()->send();
    }
}
