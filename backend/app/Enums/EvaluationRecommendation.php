<?php

namespace App\Enums;

/**
 * What an interviewer recommends about a candidate after the interview (REC-INT).
 */
enum EvaluationRecommendation: string
{
    case Continue = 'continue';
    case Consider = 'consider';
    case Reject = 'reject';

    public function label(): string
    {
        return match ($this) {
            self::Continue => 'Lanjut',
            self::Consider => 'Pertimbangkan',
            self::Reject => 'Tidak lanjut',
        };
    }
}
