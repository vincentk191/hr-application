<?php

namespace App\Support\Employees;

/**
 * The profile fields that are hidden until someone asks to see them, and how they are hidden.
 *
 * Reading the profile shows `••••1234` for these; the real value comes from a separate request that is written to
 * the activity log, so who looked at whose ID number, account number or salary can be answered afterwards. The edit
 * form is not masked: it needs the real value to change it, and only people who may edit reach it.
 */
final class SensitiveFields
{
    /**
     * Each maskable field and the group of the profile payload it sits in.
     *
     * @var array<string, string>
     */
    private const GROUPS = [
        'phone' => 'contact',
        'national_id' => 'contact',
        'emergency_contact_phone' => 'contact',
        'family_card_number' => 'identity',
        'bank_account_number' => 'bank',
        'tax_number' => 'bank',
        'bpjs_employment_number' => 'coverage',
        'bpjs_health_number' => 'coverage',
        'base_salary_idr' => 'payroll',
        'allowances_idr' => 'payroll',
        'overtime_rate_idr' => 'payroll',
    ];

    /** The groups that need the sensitive-data permission (or being the owner) on top of seeing the record. */
    private const RESTRICTED_GROUPS = ['identity', 'bank', 'coverage', 'payroll'];

    /** Amounts show nothing of themselves, not even their last digits. */
    private const AMOUNTS = ['base_salary_idr', 'allowances_idr', 'overtime_rate_idr'];

    private const DOTS = '••••';

    /**
     * @return list<string>
     */
    public static function names(): array
    {
        return array_keys(self::GROUPS);
    }

    /**
     * Whether reading this field needs the sensitive-data permission, as the bank and payroll cards do, rather than
     * only access to the record, as the contact details do.
     */
    public static function isRestricted(string $field): bool
    {
        return in_array(self::GROUPS[$field] ?? null, self::RESTRICTED_GROUPS, true);
    }

    /**
     * The profile payload with every present value replaced by its mask, and `masked` listing the fields that were
     * replaced so the page knows which ones can be revealed. Empty values stay empty: there is nothing to hide.
     *
     * @param  array<string, mixed>  $detail
     * @return array<string, mixed>
     */
    public static function maskDetail(array $detail): array
    {
        $masked = [];

        foreach (self::GROUPS as $field => $group) {
            $value = $detail[$group][$field] ?? null;

            if ($value === null || $value === '') {
                continue;
            }

            $detail[$group][$field] = self::mask($field, $value);
            $masked[] = $field;
        }

        $detail['masked'] = $masked;

        return $detail;
    }

    public static function mask(string $field, string|int|float $value): string
    {
        $text = (string) $value;

        if (in_array($field, self::AMOUNTS, true) || mb_strlen($text) <= 4) {
            return self::DOTS;
        }

        return self::DOTS.mb_substr($text, -4);
    }
}
