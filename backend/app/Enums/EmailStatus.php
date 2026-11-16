<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Where an email stands: waiting to be sent (now or at a set time), being sent, sent, failed, or cancelled before it went.
 */
enum EmailStatus: string implements HasColor, HasLabel
{
    case Pending = 'pending';
    case Sending = 'sending';
    case Sent = 'sent';
    case Failed = 'failed';
    case Cancelled = 'cancelled';

    public function getLabel(): string
    {
        return match ($this) {
            self::Pending => 'Menunggu',
            self::Sending => 'Mengirim',
            self::Sent => 'Terkirim',
            self::Failed => 'Gagal',
            self::Cancelled => 'Dibatalkan',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Pending, self::Sending => 'warning',
            self::Sent => 'success',
            self::Failed => 'danger',
            self::Cancelled => 'gray',
        };
    }
}
