<?php

namespace App\Support\Employees;

/**
 * The fixed lists the employee forms choose from where the answer must be one of a few spellings: education, a bank, the
 * emergency contact's relation and how the salary is paid. The form offers them as choices, the server accepts only them,
 * and a spreadsheet's wording is matched to them (see `match`), so the same bank is never stored two ways.
 */
final class EmployeeChoices
{
    /** @var list<string> */
    public const EDUCATION = ['SD', 'SMP', 'SMA/SMK', 'D3', 'S1', 'S2', 'S3'];

    /** @var list<string> */
    public const RELATIONS = ['Suami', 'Istri', 'Orang tua', 'Anak', 'Saudara', 'Lainnya'];

    /** @var list<string> */
    public const PAYMENT_METHODS = ['Transfer', 'Tunai'];

    /** @var list<string> */
    public const BANKS = [
        'BCA', 'Mandiri', 'BNI', 'BRI', 'BTN', 'BSI', 'CIMB Niaga', 'Danamon', 'Permata', 'Maybank', 'OCBC', 'Panin', 'Mega',
        'KB Bukopin', 'BTPN', 'Jenius', 'Jago', 'SeaBank', 'Allo Bank', 'Neo Commerce', 'Muamalat', 'Sinarmas', 'UOB', 'HSBC',
        'DBS', 'BJB', 'Bank DKI', 'Bank Jatim', 'Bank Jateng', 'Lainnya',
    ];

    /**
     * Other ways a sheet or a person writes an entry, by the key `key()` makes of them.
     *
     * @var array<string, array<string, string>>
     */
    private const ALIASES = [
        'EDUCATION' => [
            'sma' => 'SMA/SMK', 'smk' => 'SMA/SMK', 'smasmk' => 'SMA/SMK', 'smu' => 'SMA/SMK', 'sltaa' => 'SMA/SMK',
            'd3' => 'D3', 'diploma3' => 'D3', 'd4' => 'S1', 'diploma4' => 'S1', 'strata1' => 'S1', 'sarjana' => 'S1',
            'strata2' => 'S2', 'magister' => 'S2', 'strata3' => 'S3', 'doktor' => 'S3',
        ],
        'BANKS' => [
            'bankbca' => 'BCA', 'bankmandiri' => 'Mandiri', 'bankbni' => 'BNI', 'bankbri' => 'BRI', 'banktabungannegara' => 'BTN',
            'banksyariahindonesia' => 'BSI', 'mandirisyariah' => 'BSI', 'cimb' => 'CIMB Niaga', 'bankcimbniaga' => 'CIMB Niaga',
            'bankdanamon' => 'Danamon', 'bankpermata' => 'Permata', 'bankmaybank' => 'Maybank', 'bankocbcnisp' => 'OCBC',
            'ocbcnisp' => 'OCBC', 'bankpanin' => 'Panin', 'bankmega' => 'Mega', 'bukopin' => 'KB Bukopin', 'bankbukopin' => 'KB Bukopin',
            'bankjago' => 'Jago', 'bankjenius' => 'Jenius', 'banksinarmas' => 'Sinarmas', 'bankmuamalat' => 'Muamalat',
            'bankneocommerce' => 'Neo Commerce', 'bankuob' => 'UOB', 'bankdki' => 'Bank DKI', 'bankjatim' => 'Bank Jatim',
            'bankjateng' => 'Bank Jateng', 'bankbjb' => 'BJB',
        ],
        'RELATIONS' => [
            'orangtua' => 'Orang tua', 'ayah' => 'Orang tua', 'ibu' => 'Orang tua', 'bapak' => 'Orang tua',
            'kakak' => 'Saudara', 'adik' => 'Saudara', 'saudarakandung' => 'Saudara', 'suamiistri' => 'Suami',
        ],
        'PAYMENT_METHODS' => ['bank' => 'Transfer', 'transferbank' => 'Transfer', 'cash' => 'Tunai'],
    ];

    /**
     * A list as the form offers it.
     *
     * @param  list<string>  $values
     * @return list<array{value: string, label: string}>
     */
    public static function options(array $values): array
    {
        return array_map(fn (string $value): array => ['value' => $value, 'label' => $value], $values);
    }

    /**
     * The entry of the list that a text stands for, however it was spelled (capitals, spaces, "Bank BCA"), or null when
     * it is not on the list.
     *
     * @param  'EDUCATION'|'BANKS'|'RELATIONS'|'PAYMENT_METHODS'  $list
     */
    public static function match(string $list, ?string $text): ?string
    {
        $key = self::key((string) $text);

        if ($key === '') {
            return null;
        }

        foreach (constant(self::class.'::'.$list) as $entry) {
            if (self::key($entry) === $key) {
                return $entry;
            }
        }

        return self::ALIASES[$list][$key] ?? null;
    }

    private static function key(string $text): string
    {
        return (string) preg_replace('/[^a-z0-9]+/', '', mb_strtolower($text));
    }
}
