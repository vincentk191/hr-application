<?php

namespace App\Enums;

/**
 * What kind of document an employee file is (EMP-DOC-001).
 *
 * A category is a filing label only. Who may see a document is decided by its own restricted
 * flag, never by its category, so a contract is not secret by default and an "other" document
 * can be.
 */
enum DocumentCategory: string
{
    case Contract = 'contract';
    case Identity = 'identity';
    case Certificate = 'certificate';
    case Tax = 'tax';
    case Medical = 'medical';
    case Other = 'other';

    /**
     * The Indonesian label shown in the dashboard.
     */
    public function label(): string
    {
        return match ($this) {
            self::Contract => 'Kontrak',
            self::Identity => 'Identitas',
            self::Certificate => 'Sertifikat',
            self::Tax => 'Pajak',
            self::Medical => 'Medis',
            self::Other => 'Lainnya',
        };
    }
}
