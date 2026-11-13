<?php

namespace App\Support\Recruitment;

use App\Support\AppSettings;

/**
 * The words of the offer letter and of the email that tells the candidate about it, as HR set them in the admin panel
 * (REC-OFR): a title, a subject line, the paragraphs before and after the terms, the person who signs for each company,
 * and the email. `{placeholders}` are filled in for each offer. The terms themselves (position, start date, salary and
 * the rest) are not part of the wording: the letter always prints them, so editing the text can never drop them.
 *
 * @phpstan-type Signer array{name: string, title: string}
 * @phpstan-type Template array{title: string, subject: string, opening: string, closing: string, email_subject: string, email_body: string, signers: array<int, Signer>}
 */
class OfferLetterTemplate
{
    public const KEY = 'recruitment.offer_letter';

    /** What the letter prints under the signature when no title was set for the company. */
    public const SIGNER_TITLE = 'HR';

    /**
     * What a placeholder stands for: name => description, in the order the admin page lists them. Both the letter and the
     * email understand these.
     *
     * @var array<string, string>
     */
    public const PLACEHOLDERS = [
        'nama' => 'Nama kandidat',
        'posisi' => 'Nama posisi lowongan',
        'perusahaan' => 'Nama perusahaan',
        'departemen' => 'Departemen (kosong bila tidak ada)',
        'cabang' => 'Cabang (kosong bila tidak ada)',
        'atasan' => 'Atasan langsung (kosong bila tidak ada)',
        'status_kerja' => 'Status kerja, mis. PKWT 1 tahun (kosong bila tidak ada)',
        'tanggal_mulai' => 'Tanggal mulai kerja, mis. 1 Desember 2026',
        'masa_percobaan' => 'Masa percobaan, mis. 3 bulan',
        'gaji' => 'Gaji pokok per bulan, mis. Rp 4.500.000',
        'tanggal_surat' => 'Tanggal surat: hari surat dikirim, atau hari ini bila belum',
    ];

    /**
     * Understood by the email only: the letter prints its terms as a list of its own.
     *
     * @var array<string, string>
     */
    public const EMAIL_PLACEHOLDERS = [
        'ketentuan' => 'Daftar ketentuan penawaran: posisi, status kerja, tanggal mulai, masa percobaan dan gaji',
    ];

    /** Placeholders an email cannot work without: without the terms the candidate would learn nothing. */
    public const EMAIL_REQUIRED = ['ketentuan'];

    public const TITLE = 'Surat penawaran kerja';

    public const SUBJECT = 'Penawaran kerja — {posisi}';

    public const OPENING = <<<'TEXT'
Dengan hormat,

Berdasarkan proses seleksi yang telah Anda ikuti, {perusahaan} dengan senang hati menawarkan Anda untuk bergabung sebagai {posisi} dengan ketentuan sebagai berikut:
TEXT;

    public const CLOSING = <<<'TEXT'
Apabila Anda menyetujui penawaran ini, mohon menandatangani surat ini pada kolom yang tersedia dan mengembalikannya kepada kami.

Kami berharap Anda dapat bergabung dan berkembang bersama {perusahaan}.
TEXT;

    public const EMAIL_SUBJECT = 'Penawaran kerja — {posisi}';

    public const EMAIL_BODY = <<<'TEXT'
Halo {nama},

Selamat! {perusahaan} menawarkan Anda posisi {posisi} dengan ketentuan berikut:

{ketentuan}

Mohon kabari kami dengan membalas pesan ini setelah Anda mempertimbangkan penawaran ini.

HR {perusahaan}
TEXT;

    public function __construct(private AppSettings $settings) {}

    /**
     * The wording HR has set, with the built-in text for anything never set.
     *
     * @return Template
     */
    public function current(): array
    {
        $saved = $this->settings->get(self::KEY, []);
        $saved = is_array($saved) ? $saved : [];

        return [
            'title' => $this->text($saved, 'title', self::TITLE),
            'subject' => $this->text($saved, 'subject', self::SUBJECT),
            'opening' => $this->text($saved, 'opening', self::OPENING),
            'closing' => $this->text($saved, 'closing', self::CLOSING),
            'email_subject' => $this->text($saved, 'email_subject', self::EMAIL_SUBJECT),
            'email_body' => $this->text($saved, 'email_body', self::EMAIL_BODY),
            'signers' => $this->signers($saved['signers'] ?? []),
        ];
    }

    /**
     * @param  array<string, mixed>  $values  the same keys as {@see current()}; a company with no name and no title is dropped
     */
    public function save(array $values): void
    {
        $this->settings->set(self::KEY, [
            'title' => trim((string) ($values['title'] ?? '')),
            'subject' => trim((string) ($values['subject'] ?? '')),
            'opening' => $this->tidy((string) ($values['opening'] ?? '')),
            'closing' => $this->tidy((string) ($values['closing'] ?? '')),
            'email_subject' => trim((string) ($values['email_subject'] ?? '')),
            'email_body' => $this->tidy((string) ($values['email_body'] ?? '')),
            'signers' => $this->signers($values['signers'] ?? []),
        ]);
    }

    /**
     * Who signs the letters of one company: the name (none prints only the title) and the title under it.
     *
     * @return array{name: string|null, title: string}
     */
    public function signerFor(int $companyId): array
    {
        $signer = $this->current()['signers'][$companyId] ?? null;

        return [
            'name' => $signer === null || $signer['name'] === '' ? null : $signer['name'],
            'title' => $signer === null || $signer['title'] === '' ? self::SIGNER_TITLE : $signer['title'],
        ];
    }

    /**
     * The `{names}` in the letter's text that are not placeholders it understands.
     *
     * @return list<string>
     */
    public static function unknownLetterPlaceholders(string $text): array
    {
        return PlaceholderText::unknown($text, array_keys(self::PLACEHOLDERS));
    }

    /**
     * The `{names}` in the email's text that are not placeholders it understands.
     *
     * @return list<string>
     */
    public static function unknownEmailPlaceholders(string $text): array
    {
        return PlaceholderText::unknown($text, [...array_keys(self::PLACEHOLDERS), ...array_keys(self::EMAIL_PLACEHOLDERS)]);
    }

    /**
     * The required placeholders the email body leaves out.
     *
     * @return list<string>
     */
    public static function missingEmailRequired(string $body): array
    {
        $missing = [];

        foreach (self::EMAIL_REQUIRED as $name) {
            if (! str_contains($body, '{'.$name.'}')) {
                $missing[] = $name;
            }
        }

        return $missing;
    }

    /**
     * An example offer for the preview on the admin page.
     *
     * @return array<string, string>
     */
    public static function sampleValues(): array
    {
        return [
            'nama' => 'Budi Santoso',
            'posisi' => 'Staf Gudang',
            'perusahaan' => 'PT Contoh Maju',
            'departemen' => 'Gudang',
            'cabang' => 'Surabaya',
            'atasan' => 'Siti Rahma',
            'status_kerja' => 'PKWT 1 tahun',
            'tanggal_mulai' => '1 Desember 2026',
            'masa_percobaan' => '3 bulan',
            'gaji' => 'Rp 4.500.000',
            'tanggal_surat' => '4 November 2026',
            'ketentuan' => "Posisi            : Staf Gudang\nStatus kerja      : PKWT 1 tahun\nTanggal mulai     : 1 Desember 2026\nMasa percobaan    : 3 bulan\nGaji pokok/bulan  : Rp 4.500.000",
        ];
    }

    /**
     * @param  array<string, mixed>  $saved
     */
    private function text(array $saved, string $key, string $default): string
    {
        return is_string($saved[$key] ?? null) && trim($saved[$key]) !== '' ? $saved[$key] : $default;
    }

    /**
     * @return array<int, Signer>
     */
    private function signers(mixed $signers): array
    {
        $result = [];

        foreach (is_array($signers) ? $signers : [] as $companyId => $signer) {
            $name = is_array($signer) ? trim((string) ($signer['name'] ?? '')) : '';
            $title = is_array($signer) ? trim((string) ($signer['title'] ?? '')) : '';

            if ($name !== '' || $title !== '') {
                $result[(int) $companyId] = ['name' => $name, 'title' => $title];
            }
        }

        return $result;
    }

    private function tidy(string $text): string
    {
        return trim(str_replace("\r\n", "\n", $text));
    }
}
