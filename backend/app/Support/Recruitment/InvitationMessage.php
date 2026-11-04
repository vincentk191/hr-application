<?php

namespace App\Support\Recruitment;

use App\Models\Candidate;
use App\Models\Requisition;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * The words of an interview invitation (REC-INT). One text, used as the email and as the copy-paste message for
 * WhatsApp or a platform's chat, so the channels cannot drift apart.
 */
class InvitationMessage
{
    /**
     * A moment in the company's timezone, which is how every date in an invitation is written.
     */
    public static function local(CarbonInterface $moment): CarbonImmutable
    {
        return CarbonImmutable::instance($moment)->setTimezone((string) config('attendance.device_timezone'));
    }

    private const DAYS = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];

    private const MONTHS = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];

    /**
     * "Selasa, 10 November 2026", or "10 November 2026" without the weekday, in Indonesian.
     */
    public static function dayLabel(CarbonInterface $moment, bool $withWeekday = true): string
    {
        $local = self::local($moment);
        $date = $local->day.' '.self::MONTHS[$local->month - 1].' '.$local->year;

        return $withWeekday ? self::DAYS[$local->dayOfWeek].', '.$date : $date;
    }

    /**
     * "Selasa, 10 November 2026 · 10:00 WIB".
     */
    public static function whenLabel(CarbonInterface $moment): string
    {
        return self::dayLabel($moment).' · '.self::local($moment)->format('H:i').' WIB';
    }

    public static function subject(Requisition $requisition): string
    {
        $company = $requisition->company->name;

        return 'Undangan interview — '.$requisition->position_name.($company ? " ({$company})" : '');
    }

    /**
     * @param  string|null  $link  the personal link to the form, or null when the form is already filled in (a new time)
     */
    public static function text(Candidate $candidate, Requisition $requisition, ?string $link, ?CarbonInterface $linkExpiresAt, ?string $note = null): string
    {
        $company = $requisition->company->name;
        $when = $candidate->interview_at;
        $online = $candidate->interview_mode === 'online';

        $lines = [
            "Halo {$candidate->name},",
            '',
            "Terima kasih sudah melamar posisi {$requisition->position_name} di {$company}. Kami mengundang Anda untuk interview:",
            '',
            'Hari/tanggal : '.($when === null ? '-' : self::dayLabel($when)),
            'Jam          : '.($when === null ? '-' : self::local($when)->format('H:i')).' WIB',
            'Format       : '.($online ? 'Online' : 'Tatap muka'),
            ($online ? 'Tautan       : ' : 'Tempat       : ').($candidate->interview_place ?? '-'),
        ];

        if ($link !== null) {
            $lines[] = '';
            $lines[] = 'Sebelum interview, mohon lengkapi formulir singkat berikut'
                .($linkExpiresAt !== null ? ' (berlaku sampai '.self::dayLabel($linkExpiresAt, false).')' : '').':';
            $lines[] = $link;
        }

        if (filled($note)) {
            $lines[] = '';
            $lines[] = trim((string) $note);
        }

        array_push($lines, '', 'Mohon konfirmasi kehadiran dengan membalas pesan ini. Terima kasih.', '', "HR {$company}");

        return implode("\n", $lines);
    }
}
