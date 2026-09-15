<?php

declare(strict_types=1);

namespace App\Graph;

final readonly class CompaniesHouseFields
{
    /**
     * @param  array<string, mixed>  $row
     */
    public static function companyNumber(array $row): ?string
    {
        $value = self::first($row, 'company_number', 'CompanyNumber', 'Company Number');

        if ($value === null) {
            $id = self::first($row, 'id');

            if ($id !== null && ! str_starts_with($id, 'ch_') && $id !== 'ch_companies' && $id !== 'ch_psc') {
                $value = $id;
            }
        }

        if ($value === null) {
            return null;
        }

        $padded = self::padCompanyNumber($value);

        return $padded === '' ? null : $padded;
    }

    public static function padCompanyNumber(string $value): string
    {
        $value = mb_strtoupper(mb_trim($value));

        if ($value === '') {
            return '';
        }

        if (preg_match('/^\d+$/', $value) === 1) {
            return mb_str_pad($value, 8, '0', STR_PAD_LEFT);
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public static function name(array $row): ?string
    {
        return self::first($row, 'name', 'CompanyName', 'Company Name');
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public static function companyStatus(array $row): ?string
    {
        return self::first($row, 'companyStatus', 'CompanyStatus', 'Company Status');
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public static function companyCategory(array $row): ?string
    {
        return self::first($row, 'companyCategory', 'CompanyCategory', 'Company Category');
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public static function countryOfOrigin(array $row): ?string
    {
        return self::first($row, 'countryOfOrigin', 'CountryOfOrigin', 'CountryofOrigin', 'Country of Origin');
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public static function incorporationDate(array $row): ?string
    {
        return self::first($row, 'incorporationDate', 'IncorporationDate', 'Incorporation Date');
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public static function dissolutionDate(array $row): ?string
    {
        return self::first($row, 'dissolutionDate', 'DissolutionDate', 'Dissolution Date');
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public static function uri(array $row): ?string
    {
        return self::first($row, 'uri', 'URI');
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public static function registeredOffice(array $row): ?string
    {
        $joined = self::first($row, 'address', 'registeredOffice');

        if ($joined !== null) {
            return $joined;
        }

        $lines = [];

        foreach ([
            'RegAddress.CareOf',
            'RegAddress.POBox',
            'RegAddress.AddressLine1',
            'RegAddress.AddressLine2',
            'RegAddress.PostTown',
            'RegAddress.County',
            'RegAddress.Country',
            'RegAddress.PostCode',
            'Careof',
            'POBox',
            'AddressLine1',
            'AddressLine2',
            'PostTown',
            'County',
            'Country',
            'PostCode',
        ] as $key) {
            $value = self::first($row, $key);

            if ($value !== null && ! in_array($value, $lines, true)) {
                $lines[] = $value;
            }
        }

        return $lines === [] ? null : implode(', ', $lines);
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public static function previousNames(array $row): ?string
    {
        $names = [];

        for ($index = 1; $index <= 10; $index++) {
            $name = self::first($row, 'PreviousName_'.$index.'.CompanyName');
            $date = self::first($row, 'PreviousName_'.$index.'.CONDATE');

            if ($name === null) {
                continue;
            }

            $names[] = $date === null ? $name : $name.' ('.$date.')';
        }

        $joined = self::first($row, 'previousNames');

        if ($joined !== null && $names === []) {
            return $joined;
        }

        return $names === [] ? null : implode(', ', $names);
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public static function sicCodes(array $row): ?string
    {
        $codes = [];

        for ($index = 1; $index <= 4; $index++) {
            $code = self::first(
                $row,
                'SICCode.SicText_'.$index,
                'SICCode'.$index,
                'SicText_'.$index,
            );

            if ($code !== null) {
                $codes[] = $code;
            }
        }

        $joined = self::first($row, 'sicCodes');

        if ($joined !== null && $codes === []) {
            return $joined;
        }

        return $codes === [] ? null : implode(', ', $codes);
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private static function first(array $row, string ...$keys): ?string
    {
        foreach ($keys as $key) {
            $value = self::lookup($row, $key);

            if ($value !== null) {
                return $value;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private static function lookup(array $row, string $key): ?string
    {
        if (array_key_exists($key, $row)) {
            return self::string($row[$key]);
        }

        $wanted = self::headerKey($key);

        foreach ($row as $header => $value) {
            if (self::headerKey((string) $header) === $wanted) {
                return self::string($value);
            }
        }

        return null;
    }

    private static function headerKey(string $header): string
    {
        return (string) preg_replace('/[^a-z0-9]+/', '', mb_strtolower(mb_trim($header)));
    }

    private static function string(mixed $value): ?string
    {
        if (! is_string($value) && ! is_int($value) && ! is_float($value)) {
            return null;
        }

        $string = mb_trim((string) $value);

        return $string === '' ? null : $string;
    }
}
