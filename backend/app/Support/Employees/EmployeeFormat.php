<?php

namespace App\Support\Employees;

use Illuminate\Validation\Rule;

/**
 * How the employee profile's numbers and names are written, in one place: the profile form, the self-service change request
 * and the spreadsheet import all use these, so a value that passes one passes the others.
 *
 * Numbers are kept as digits only (dots, dashes and spaces are typed or pasted and are stripped before checking), a phone
 * number as 08…, and RT/RW padded to three digits.
 */
final class EmployeeFormat
{
    public const NAME = '/^[\p{L}][\p{L} .\'’-]*$/u';

    /** The patterns and their messages, by profile field. */
    private const PATTERNS = [
        'employee_number' => ['/^[A-Za-z0-9-]+$/', 'ID karyawan hanya boleh huruf, angka, dan tanda hubung, tanpa spasi.'],
        'phone' => ['/^08\d{8,11}$/', 'Telepon harus nomor HP yang diawali 08 (10–13 digit).'],
        'emergency_contact_phone' => ['/^08\d{8,11}$/', 'Telepon darurat harus nomor HP yang diawali 08 (10–13 digit).'],
        'national_id' => ['/^\d{16}$/', 'NIK harus 16 digit angka.'],
        'family_card_number' => ['/^\d{16}$/', 'No. KK harus 16 digit angka.'],
        'tax_number' => ['/^\d{15,16}$/', 'NPWP harus 15 atau 16 digit angka.'],
        'bpjs_employment_number' => ['/^\d{11}$/', 'No. BPJS Ketenagakerjaan harus 11 digit angka.'],
        'bpjs_health_number' => ['/^\d{13}$/', 'No. BPJS Kesehatan harus 13 digit angka.'],
        'bank_account_number' => ['/^\d{6,20}$/', 'No. rekening harus 6–20 digit angka.'],
        'id_rt' => ['/^\d{3}$/', 'RT harus 3 digit angka, mis. 001.'],
        'id_rw' => ['/^\d{3}$/', 'RW harus 3 digit angka, mis. 001.'],
        'residence_postal_code' => ['/^\d{5}$/', 'Kode pos harus 5 digit angka.'],
        'id_postal_code' => ['/^\d{5}$/', 'Kode pos KTP harus 5 digit angka.'],
        'full_name' => [self::NAME, 'Nama hanya boleh huruf, spasi, titik, apostrof, dan tanda hubung.'],
        'emergency_contact_name' => [self::NAME, 'Nama kontak darurat hanya boleh huruf, spasi, titik, apostrof, dan tanda hubung.'],
        'bank_account_holder' => [self::NAME, 'Nama pemilik rekening hanya boleh huruf, spasi, titik, apostrof, dan tanda hubung.'],
        'mother_name' => [self::NAME, 'Nama ibu hanya boleh huruf, spasi, titik, apostrof, dan tanda hubung.'],
        'birth_place' => [self::NAME, 'Tempat lahir hanya boleh huruf, spasi, titik, apostrof, dan tanda hubung.'],
    ];

    /** Fields kept as digits only. */
    public const DIGIT_FIELDS = [
        'national_id', 'family_card_number', 'tax_number', 'bpjs_employment_number', 'bpjs_health_number',
        'bank_account_number', 'residence_postal_code', 'id_postal_code',
    ];

    public const PHONE_FIELDS = ['phone', 'emergency_contact_phone'];

    public const PAD_FIELDS = ['id_rt', 'id_rw'];

    /** The lists a field must be chosen from, by field. */
    private const LISTS = [
        'education_level' => ['EDUCATION', 'Pilih pendidikan terakhir dari daftar.'],
        'bank_name' => ['BANKS', 'Pilih bank dari daftar.'],
        'emergency_contact_relation' => ['RELATIONS', 'Pilih hubungan dari daftar.'],
        'payment_method' => ['PAYMENT_METHODS', 'Pilih metode pembayaran dari daftar.'],
    ];

    /**
     * The format rules of a field (without "required" or "nullable"), for the forms and the change request.
     *
     * @return list<mixed>
     */
    public static function rules(string $field): array
    {
        if (isset(self::PATTERNS[$field])) {
            [$pattern] = self::PATTERNS[$field];

            return ['string', 'regex:'.$pattern, ...($pattern === self::NAME ? ['min:2'] : [])];
        }

        if (isset(self::LISTS[$field])) {
            return [Rule::in(constant(EmployeeChoices::class.'::'.self::LISTS[$field][0]))];
        }

        return [];
    }

    /**
     * What a form needs to help someone type a field right: the choices of a list, the keyboard (`tel` or `numeric`) and the
     * longest length of a number of fixed size. Empty for a field with nothing to say.
     *
     * @return array{options?: list<string>, inputMode?: string, maxLength?: int}
     */
    public static function hints(string $field): array
    {
        $hints = [];

        if (isset(self::LISTS[$field])) {
            $hints['options'] = constant(EmployeeChoices::class.'::'.self::LISTS[$field][0]);
        }

        if (in_array($field, self::PHONE_FIELDS, true)) {
            $hints['inputMode'] = 'tel';
            $hints['maxLength'] = 16;
        } elseif (in_array($field, [...self::DIGIT_FIELDS, ...self::PAD_FIELDS], true)) {
            $hints['inputMode'] = 'numeric';
            $hints['maxLength'] = match ($field) {
                'national_id', 'family_card_number', 'tax_number' => 16,
                'bpjs_employment_number' => 11,
                'bpjs_health_number' => 13,
                'bank_account_number' => 20,
                'residence_postal_code', 'id_postal_code' => 5,
                default => 3,
            };
        }

        return $hints;
    }

    /**
     * The messages for every field this class has a rule for.
     *
     * @return array<string, string>
     */
    public static function messages(): array
    {
        $messages = [];

        foreach (self::PATTERNS as $field => [, $message]) {
            $messages["{$field}.regex"] = $message;
        }

        foreach (self::LISTS as $field => [, $message]) {
            $messages["{$field}.in"] = $message;
        }

        return $messages;
    }

    /**
     * The values of a request or a spreadsheet row as they are stored: digits only where a number is meant, a phone as 08…,
     * RT and RW to three digits, a list entry in its listed spelling, an email in lower case, and blanks as null. A value that
     * cannot be understood is left as typed, so the rules report it.
     *
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    public static function normalise(array $values): array
    {
        foreach ($values as $field => $value) {
            if (! is_string($value)) {
                continue;
            }

            $value = trim($value);

            if (in_array($field, self::DIGIT_FIELDS, true)) {
                $value = preg_replace('/\D+/', '', $value) ?? $value;
            } elseif (in_array($field, self::PHONE_FIELDS, true)) {
                $value = self::phone($value);
            } elseif (in_array($field, self::PAD_FIELDS, true) && preg_match('/^\d{1,3}$/', $value) === 1) {
                $value = str_pad($value, 3, '0', STR_PAD_LEFT);
            } elseif ($field === 'email') {
                $value = mb_strtolower($value);
            } elseif (isset(self::LISTS[$field])) {
                $value = EmployeeChoices::match(self::LISTS[$field][0], $value) ?? $value;
            }

            $values[$field] = $value === '' ? null : $value;
        }

        return $values;
    }

    /**
     * "+62 812-3456-7890" and "62812…" as "0812…".
     */
    public static function phone(string $value): string
    {
        $digits = preg_replace('/[^\d+]/', '', $value) ?? '';

        if (str_starts_with($digits, '+62')) {
            return '0'.substr($digits, 3);
        }

        if (str_starts_with($digits, '62')) {
            return '0'.substr($digits, 2);
        }

        return $digits;
    }
}
