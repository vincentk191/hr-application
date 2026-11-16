<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * What an email is for, as the email log shows it. A kind is added with the feature that sends it.
 */
enum EmailKind: string implements HasLabel
{
    case Test = 'test';

    public function getLabel(): string
    {
        return match ($this) {
            self::Test => 'Email uji',
        };
    }
}
