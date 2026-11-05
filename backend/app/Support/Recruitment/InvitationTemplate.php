<?php

namespace App\Support\Recruitment;

use App\Support\AppSettings;

/**
 * The words of the interview invitation as HR set them in the admin panel (REC-INT): a subject and a body with
 * `{placeholders}` that are filled in for each candidate. The same body is the email and the copy-paste text for
 * WhatsApp. What was sent is stored with each candidate, so changing the template never rewrites an invitation already
 * made.
 */
class InvitationTemplate
{
    public const KEY = 'recruitment.invitation';

    /**
     * What a placeholder stands for: name => description, in the order the admin page lists them.
     *
     * @var array<string, string>
     */
    public const PLACEHOLDERS = [
        'nama' => 'Nama kandidat',
        'posisi' => 'Nama posisi lowongan',
        'perusahaan' => 'Nama perusahaan',
        'hari_tanggal' => 'Hari dan tanggal interview, mis. Selasa, 10 November 2026',
        'jam' => 'Jam interview, mis. 10:00',
        'format' => 'Daring atau Tatap muka',
        'tempat_label' => 'Kata "Tautan" (daring) atau "Tempat" (tatap muka)',
        'tempat' => 'Tautan Google Meet atau alamat tempat interview',
        'formulir' => 'Paragraf dan tautan formulir kandidat (kosong bila formulir sudah diisi)',
        'catatan' => 'Catatan HR untuk kandidat itu (kosong bila tidak ada)',
    ];

    /** Placeholders an invitation cannot work without: when it is, and the form's link. */
    public const REQUIRED = ['hari_tanggal', 'jam', 'formulir'];

    public const SUBJECT = 'Undangan interview — {posisi} ({perusahaan})';

    public const BODY = <<<'TEXT'
Halo {nama},

Terima kasih sudah melamar posisi {posisi} di {perusahaan}. Kami mengundang Anda untuk interview:

Hari/tanggal : {hari_tanggal}
Jam          : {jam} WIB
Format       : {format}
{tempat_label} : {tempat}

{formulir}

{catatan}

Mohon konfirmasi kehadiran dengan membalas pesan ini. Terima kasih.

HR {perusahaan}
TEXT;

    public function __construct(private AppSettings $settings) {}

    /**
     * The subject and body HR has set, or the built-in ones for what was never set.
     *
     * @return array{subject: string, body: string}
     */
    public function current(): array
    {
        $saved = $this->settings->get(self::KEY, []);
        $saved = is_array($saved) ? $saved : [];

        return [
            'subject' => is_string($saved['subject'] ?? null) && trim($saved['subject']) !== '' ? $saved['subject'] : self::SUBJECT,
            'body' => is_string($saved['body'] ?? null) && trim($saved['body']) !== '' ? $saved['body'] : self::BODY,
        ];
    }

    public function save(string $subject, string $body): void
    {
        $this->settings->set(self::KEY, ['subject' => trim($subject), 'body' => $this->tidy($body)]);
    }

    /**
     * Fill the placeholders in a text. A placeholder with no value becomes empty, and the blank lines that leaves are
     * collapsed so an absent note or form paragraph does not leave a gap.
     *
     * @param  array<string, string>  $values
     */
    public static function render(string $template, array $values): string
    {
        $text = preg_replace_callback('/\{([a-z_]+)\}/', fn (array $found): string => $values[$found[1]] ?? $found[0], $template) ?? $template;

        return trim(preg_replace("/\n{3,}/", "\n\n", str_replace("\r\n", "\n", $text)) ?? $text);
    }

    /**
     * The `{names}` in a text that are not placeholders, so a typo is caught when saving instead of reaching a candidate.
     *
     * @return list<string>
     */
    public static function unknownPlaceholders(string $text): array
    {
        preg_match_all('/\{([^{}\s]*)\}/', $text, $found);

        return array_values(array_unique(array_filter(
            $found[1],
            fn (string $name): bool => ! array_key_exists($name, self::PLACEHOLDERS),
        )));
    }

    /**
     * The required placeholders the body leaves out.
     *
     * @return list<string>
     */
    public static function missingRequired(string $body): array
    {
        return array_values(array_filter(self::REQUIRED, fn (string $name): bool => ! str_contains($body, '{'.$name.'}')));
    }

    private function tidy(string $body): string
    {
        return trim(str_replace("\r\n", "\n", $body));
    }
}
