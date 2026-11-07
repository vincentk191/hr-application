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

    /**
     * The subject line, from the template HR set.
     */
    public static function subject(Requisition $requisition): string
    {
        return InvitationTemplate::render(app(InvitationTemplate::class)->current()['subject'], [
            'posisi' => $requisition->position_name,
            'perusahaan' => $requisition->company->name,
        ]);
    }

    /**
     * The invitation text for a candidate, from the template HR set.
     *
     * @param  string|null  $link  the personal link to the form, or null when the form is already filled in (a new time)
     */
    public static function text(Candidate $candidate, Requisition $requisition, ?string $link, ?string $note = null): string
    {
        $when = $candidate->interview_at;
        $online = $candidate->interview_mode === 'online';

        $form = $link === null ? '' : 'Pada hari interview'
            .($when === null ? '' : ' ('.self::dayLabel($when, false).')')
            .", mohon lengkapi formulir singkat berikut. Tautan ini hanya bisa dibuka pada hari itu dan hanya sekali pakai:\n".$link;

        return InvitationTemplate::render(app(InvitationTemplate::class)->current()['body'], [
            'nama' => $candidate->name,
            'posisi' => $requisition->position_name,
            'perusahaan' => $requisition->company->name,
            'hari_tanggal' => $when === null ? '-' : self::dayLabel($when),
            'jam' => $when === null ? '-' : self::local($when)->format('H:i'),
            'format' => $online ? 'Daring' : 'Tatap muka',
            'tempat_label' => str_pad($online ? 'Tautan' : 'Tempat', 12),
            'tempat' => $candidate->interview_place ?? '-',
            'formulir' => $form,
            'catatan' => trim((string) $note),
        ]);
    }

    /**
     * The day-before reminder: when and where, nothing else. The form's link is not repeated, because it opens only on the
     * interview day and the invitation already carries it.
     */
    public static function reminder(Candidate $candidate, Requisition $requisition): string
    {
        $when = $candidate->interview_at;
        $online = $candidate->interview_mode === 'online';

        return implode("\n", [
            "Halo {$candidate->name},",
            '',
            "Mengingatkan interview untuk posisi {$requisition->position_name} di {$requisition->company->name} besok:",
            '',
            'Hari/tanggal : '.($when === null ? '-' : self::dayLabel($when)),
            'Jam          : '.($when === null ? '-' : self::local($when)->format('H:i')).' WIB',
            'Format       : '.($online ? 'Online' : 'Tatap muka'),
            str_pad($online ? 'Tautan' : 'Tempat', 12).' : '.($candidate->interview_place ?? '-'),
            '',
            'Jika berhalangan, mohon kabari kami dengan membalas pesan ini. Terima kasih.',
            '',
            "HR {$requisition->company->name}",
        ]);
    }

    /**
     * Example values for the admin page's preview: a candidate invited to an online interview on a future date.
     *
     * @return array<string, string>
     */
    public static function sampleValues(): array
    {
        return [
            'nama' => 'Budi Santoso',
            'posisi' => 'Staf Gudang',
            'perusahaan' => 'PT Contoh Maju',
            'hari_tanggal' => 'Selasa, 10 November 2026',
            'jam' => '10:00',
            'format' => 'Daring',
            'tempat_label' => str_pad('Tautan', 12),
            'tempat' => 'https://meet.google.com/abc-defg-hij',
            'formulir' => "Pada hari interview (10 November 2026), mohon lengkapi formulir singkat berikut. Tautan ini hanya bisa dibuka pada hari itu dan hanya sekali pakai:\nhttps://hr.example.com/lamaran/xxxxxxxx",
            'catatan' => 'Mohon hadir 10 menit lebih awal.',
        ];
    }
}
