<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * What an email is for, as the email log shows it. A kind is added with the feature that sends it.
 */
enum EmailKind: string implements HasLabel
{
    case Test = 'test';
    case OfferSent = 'offer_sent';
    case OfferWithdrawn = 'offer_withdrawn';
    case CandidateRejected = 'candidate_rejected';
    case PositionFilled = 'position_filled';
    case OnboardingWelcome = 'onboarding_welcome';

    public function getLabel(): string
    {
        return match ($this) {
            self::Test => 'Email uji',
            self::OfferSent => 'Penawaran kerja',
            self::OfferWithdrawn => 'Penawaran ditarik',
            self::CandidateRejected => 'Penolakan kandidat',
            self::PositionFilled => 'Lowongan terisi',
            self::OnboardingWelcome => 'Sambutan karyawan baru',
        };
    }
}
