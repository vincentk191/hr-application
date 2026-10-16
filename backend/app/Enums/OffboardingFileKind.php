<?php

namespace App\Enums;

/**
 * What a file on an offboarding case is (OFF-WZ1-006).
 */
enum OffboardingFileKind: string
{
    case ResignationLetter = 'resignation_letter';
    case Notice = 'notice';
    case Handover = 'handover';
    case ReturnEvidence = 'return_evidence';
    case ExitInterviewNotes = 'exit_interview_notes';
    case ReferenceLetter = 'reference_letter';
    case Other = 'other';

    /**
     * The Indonesian label shown in the dashboard.
     */
    public function label(): string
    {
        return match ($this) {
            self::ResignationLetter => 'Surat pengunduran diri',
            self::Notice => 'Surat pemberitahuan',
            self::Handover => 'Catatan serah terima',
            self::ReturnEvidence => 'Bukti pengembalian',
            self::ExitInterviewNotes => 'Catatan wawancara keluar',
            self::ReferenceLetter => 'Surat referensi',
            self::Other => 'Berkas lain',
        };
    }
}
