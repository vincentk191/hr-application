<?php

namespace App\Enums;

/**
 * The PTKP status that sets an employee's non-taxable income: TK single, K married, K/I married with the spouse's
 * income combined, each followed by the number of dependants (0 to 3).
 */
enum PtkpStatus: string
{
    case Tk0 = 'TK/0';
    case Tk1 = 'TK/1';
    case Tk2 = 'TK/2';
    case Tk3 = 'TK/3';
    case K0 = 'K/0';
    case K1 = 'K/1';
    case K2 = 'K/2';
    case K3 = 'K/3';
    case Ki0 = 'K/I/0';
    case Ki1 = 'K/I/1';
    case Ki2 = 'K/I/2';
    case Ki3 = 'K/I/3';

    public function label(): string
    {
        return $this->value;
    }

    /**
     * Read a spreadsheet cell ("TK0", "tk/0", "K / 1", "K/I/2" ...), or null when it is not a PTKP status.
     */
    public static function fromSheet(string $value): ?self
    {
        $key = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $value) ?? '');

        foreach (self::cases() as $case) {
            if (str_replace('/', '', $case->value) === $key) {
                return $case;
            }
        }

        return null;
    }
}
