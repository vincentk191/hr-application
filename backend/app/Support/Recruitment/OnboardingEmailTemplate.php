<?php

namespace App\Support\Recruitment;

use App\Models\CandidateOffer;
use App\Models\Requisition;
use App\Support\AppSettings;

/**
 * The words of the welcome email a hire gets when HR marks their offer accepted (email automation, Phase 3): when and
 * where to come on the first day, what to bring, and how to open an account. HR sets the subject and the text in the admin
 * panel; `{placeholders}` are filled in for each hire, and HR reviews and may edit the result before it goes.
 *
 * Nothing private goes in it: not the salary, and not the NIK or the employee number (the hire is told to ask HR for it).
 * The time and place of the first day are not known to the app, so `{waktu_tempat}` stays an empty slot ({@see BLANK}) that
 * HR fills in while reviewing, and the email is refused while it is still there.
 */
class OnboardingEmailTemplate
{
    public const KEY = 'recruitment.onboarding_email';

    /** What `{waktu_tempat}` shows until HR writes the real time and place over it. */
    public const BLANK = '[isi waktu dan tempat]';

    /**
     * What a placeholder stands for: name => description, in the order the admin page lists them.
     *
     * @var array<string, string>
     */
    public const PLACEHOLDERS = [
        'nama' => 'Nama karyawan baru',
        'posisi' => 'Nama posisi',
        'perusahaan' => 'Nama perusahaan',
        'departemen' => 'Departemen (kosong bila tidak ada)',
        'cabang' => 'Cabang (kosong bila tidak ada)',
        'atasan' => 'Atasan (kosong bila tidak ada)',
        'tanggal_mulai' => 'Tanggal mulai kerja, mis. 1 Desember 2026',
        'waktu_tempat' => 'Waktu dan tempat hari pertama: HR mengisinya sebelum email dikirim',
        'alamat_daftar' => 'Alamat halaman Daftar untuk membuat akun',
    ];

    public const SUBJECT = 'Selamat bergabung di {perusahaan}';

    public const BODY = <<<'TEXT'
Halo {nama},

Terima kasih telah menerima penawaran kami. Selamat bergabung di {perusahaan} sebagai {posisi}!

Hari pertama Anda: {tanggal_mulai}
Waktu dan tempat: {waktu_tempat}
Atasan: {atasan}

Mohon membawa:
- KTP asli dan fotokopinya
- Pas foto terbaru
- Fotokopi ijazah terakhir
- Nomor rekening bank atas nama Anda
- NPWP (bila ada)

Setelah Anda resmi tercatat sebagai karyawan, HR akan memberi tahu nomor karyawan Anda secara langsung. Dengan nomor itu dan NIK Anda, buat akun di {alamat_daftar} dengan memilih "Daftar".

Bila ada pertanyaan sebelum hari pertama, balas email ini.

HR {perusahaan}
TEXT;

    public function __construct(private AppSettings $settings) {}

    /**
     * The wording HR has set, or the built-in one for what was never set.
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
        $this->settings->set(self::KEY, ['subject' => trim($subject), 'body' => trim(str_replace("\r\n", "\n", $body))]);
    }

    /**
     * The email to one hire, with `{waktu_tempat}` left as the empty slot HR fills in.
     *
     * @return array{subject: string, body: string}
     */
    public function render(Requisition $requisition, CandidateOffer $offer): array
    {
        $requisition->loadMissing('company', 'department', 'branch', 'manager');
        $offer->loadMissing('candidate');
        $wording = $this->current();
        $values = [
            'nama' => $offer->candidate->name,
            'posisi' => $requisition->position_name,
            'perusahaan' => $requisition->company->name,
            'departemen' => $requisition->department->name ?? '',
            'cabang' => $requisition->branch->name ?? '',
            'atasan' => $requisition->manager->full_name ?? '',
            'tanggal_mulai' => $offer->start_on === null ? '-' : InvitationMessage::dayLabel($offer->start_on, false),
            'waktu_tempat' => self::BLANK,
            'alamat_daftar' => self::registerAddress(),
        ];

        return ['subject' => PlaceholderText::fill($wording['subject'], $values), 'body' => PlaceholderText::fill($wording['body'], $values)];
    }

    /**
     * Whether the text still has the empty slot for the time and place.
     */
    public static function hasBlank(string $text): bool
    {
        return str_contains($text, self::BLANK);
    }

    /**
     * The address of the page where the hire makes an account. It is on the **public** address: the hire is not on the VPN.
     */
    public static function registerAddress(): string
    {
        $path = route('register', absolute: false);
        $base = config('app.public_url');

        return filled($base) ? rtrim((string) $base, '/').$path : url($path);
    }

    /**
     * The `{names}` in a text that are not placeholders, so a typo is caught when saving instead of reaching a hire.
     *
     * @return list<string>
     */
    public static function unknownPlaceholders(string $text): array
    {
        return PlaceholderText::unknown($text, array_keys(self::PLACEHOLDERS));
    }

    /**
     * Example values for the admin page's preview.
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
            'tanggal_mulai' => '1 Desember 2026',
            'waktu_tempat' => self::BLANK,
            'alamat_daftar' => 'https://hr.contoh.id/register',
        ];
    }
}
