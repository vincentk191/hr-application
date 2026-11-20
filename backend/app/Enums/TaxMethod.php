<?php

namespace App\Enums;

/**
 * How a company pays the tax on its employees' pay: grossed up (the company bears it) or netto. Printed on the employee
 * induction form as a pair of boxes.
 */
enum TaxMethod: string
{
    case GrossUp = 'gross_up';
    case Netto = 'netto';

    /**
     * The Indonesian label shown in the dashboard and on the form.
     */
    public function label(): string
    {
        return match ($this) {
            self::GrossUp => 'Gross Up',
            self::Netto => 'Netto',
        };
    }
}
