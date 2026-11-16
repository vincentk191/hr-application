<?php

namespace App\Support\Recruitment;

use App\Models\Candidate;
use App\Models\Requisition;
use App\Support\AppSettings;
use Carbon\CarbonImmutable;

/**
 * The words of the emails that tell a candidate how the selection ended (email automation): that HR turned them down after
 * the interview, and that the position was filled by someone else. HR sets the subject and the text of each in the admin
 * panel; `{placeholders}` are filled in for each candidate. The reason HR gave for turning someone down is internal and is
 * never a placeholder, so it cannot reach a candidate.
 *
 * Both go out the following morning, not at once ({@see nextMorning()}).
 *
 * @phpstan-type Wording array{subject: string, body: string}
 */
class CandidateOutcomeTemplate
{
    public const KEY = 'recruitment.outcome_emails';

    public const REJECTION = 'rejection';

    public const POSITION_FILLED = 'position_filled';

    /** The kinds, in the order the admin page lists them, with what the admin page calls each. */
    public const KINDS = [
        self::REJECTION => 'Penolakan setelah interview',
        self::POSITION_FILLED => 'Lowongan sudah terisi',
    ];

    /** When the email goes out the next day, in the company's time. */
    public const SEND_HOUR = 8;

    /**
     * What a placeholder stands for: name => description, in the order the admin page lists them.
     *
     * @var array<string, string>
     */
    public const PLACEHOLDERS = [
        'nama' => 'Nama kandidat',
        'posisi' => 'Nama posisi lowongan',
        'perusahaan' => 'Nama perusahaan',
    ];

    public const SUBJECT = 'Status lamaran — {posisi}';

    public const REJECTION_BODY = <<<'TEXT'
Halo {nama},

Terima kasih atas waktu dan minat Anda pada posisi {posisi} di {perusahaan}.

Setelah kami pertimbangkan, kami belum dapat melanjutkan proses Anda untuk posisi ini. Kami menghargai usaha Anda dan berharap Anda sukses ke depannya.

HR {perusahaan}
TEXT;

    public const POSITION_FILLED_BODY = <<<'TEXT'
Halo {nama},

Terima kasih atas waktu dan minat Anda pada posisi {posisi} di {perusahaan}.

Posisi ini sudah terisi, sehingga kami tidak dapat melanjutkan proses Anda. Kami menghargai usaha Anda dan berharap Anda sukses ke depannya.

HR {perusahaan}
TEXT;

    public function __construct(private AppSettings $settings) {}

    /**
     * The built-in wording of a kind.
     *
     * @return Wording
     */
    public static function defaults(string $kind): array
    {
        return ['subject' => self::SUBJECT, 'body' => $kind === self::REJECTION ? self::REJECTION_BODY : self::POSITION_FILLED_BODY];
    }

    /**
     * The wording HR has set for a kind, or the built-in one for what was never set.
     *
     * @return Wording
     */
    public function current(string $kind): array
    {
        $saved = $this->settings->get(self::KEY, []);
        $saved = is_array($saved) && is_array($saved[$kind] ?? null) ? $saved[$kind] : [];
        $defaults = self::defaults($kind);

        return [
            'subject' => is_string($saved['subject'] ?? null) && trim($saved['subject']) !== '' ? $saved['subject'] : $defaults['subject'],
            'body' => is_string($saved['body'] ?? null) && trim($saved['body']) !== '' ? $saved['body'] : $defaults['body'],
        ];
    }

    public function save(string $kind, string $subject, string $body): void
    {
        $all = $this->settings->get(self::KEY, []);
        $all = is_array($all) ? $all : [];
        $all[$kind] = ['subject' => trim($subject), 'body' => trim(str_replace("\r\n", "\n", $body))];

        $this->settings->set(self::KEY, $all);
    }

    /**
     * The email to one candidate: the wording of the kind with this candidate and requisition filled in.
     *
     * @param  string|null  $name  what to put for the candidate's name instead of theirs (the preview shown for a whole group)
     * @return Wording
     */
    public function render(string $kind, Candidate $candidate, Requisition $requisition, ?string $name = null): array
    {
        $requisition->loadMissing('company');
        $wording = $this->current($kind);
        $values = ['nama' => $name ?? $candidate->name, 'posisi' => $requisition->position_name, 'perusahaan' => $requisition->company->name];

        return ['subject' => PlaceholderText::fill($wording['subject'], $values), 'body' => PlaceholderText::fill($wording['body'], $values)];
    }

    /**
     * The `{names}` in a text that are not placeholders, so a typo is caught when saving instead of reaching a candidate.
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
        return ['nama' => 'Budi Santoso', 'posisi' => 'Staf Gudang', 'perusahaan' => 'PT Contoh Maju'];
    }

    /**
     * When an outcome email goes out: the next morning at {@see SEND_HOUR}, company time. Not at once, so a candidate does not
     * hear a no minutes after the decision, and so HR can take a rejection back before it goes.
     */
    public static function nextMorning(?CarbonImmutable $from = null): CarbonImmutable
    {
        $timezone = (string) config('attendance.device_timezone');

        return ($from ?? CarbonImmutable::now())->setTimezone($timezone)->addDay()->setTime(self::SEND_HOUR, 0);
    }
}
