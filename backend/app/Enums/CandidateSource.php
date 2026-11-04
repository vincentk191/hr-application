<?php

namespace App\Enums;

/**
 * Where a shortlisted candidate came from (REC-SCR): one of the job platforms, or a walk-in or a referral, which have
 * no platform to import from.
 */
enum CandidateSource: string
{
    case Glints = 'glints';
    case JobStreet = 'jobstreet';
    case LinkedIn = 'linkedin';
    case Indeed = 'indeed';
    case Kalibrr = 'kalibrr';
    case WalkIn = 'walk_in';
    case Referral = 'referral';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Glints => 'Glints',
            self::JobStreet => 'JobStreet',
            self::LinkedIn => 'LinkedIn',
            self::Indeed => 'Indeed',
            self::Kalibrr => 'Kalibrr',
            self::WalkIn => 'Walk-in',
            self::Referral => 'Referral',
            self::Other => 'Lainnya',
        };
    }
}
