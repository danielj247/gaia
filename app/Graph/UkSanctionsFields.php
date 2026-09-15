<?php

declare(strict_types=1);

namespace App\Graph;

final readonly class UkSanctionsFields
{
    /**
     * @param  array<string, mixed>  $row
     */
    public static function uniqueId(array $row): ?string
    {
        return self::first($row, 'uniqueId', 'Unique ID');
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public static function ofsiGroupId(array $row): ?string
    {
        return self::first($row, 'ofsiGroupId', 'OFSI Group ID');
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public static function type(array $row): ?string
    {
        return self::first($row, 'type', 'Designation Type', 'Individual, Entity, Ship');
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public static function regime(array $row): ?string
    {
        return self::first($row, 'regime', 'Regime Name');
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public static function sanctionsImposed(array $row): ?string
    {
        return self::first($row, 'sanctionsImposed', 'Sanctions Imposed');
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public static function dateOfBirth(array $row): ?string
    {
        return self::first($row, 'dateOfBirth', 'D.O.B');
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public static function position(array $row): ?string
    {
        return self::first($row, 'position', 'Position');
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public static function gender(array $row): ?string
    {
        return self::first($row, 'gender', 'Gender');
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public static function imo(array $row): ?string
    {
        return self::first($row, 'imo', 'IMO number');
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public static function nameType(array $row): ?string
    {
        return self::first($row, 'nameType', 'Name type');
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public static function assembledName(array $row): ?string
    {
        $canonical = self::string($row['name'] ?? null);

        if ($canonical !== null) {
            return $canonical;
        }

        $parts = [];

        foreach (['Name 1', 'Name 2', 'Name 3', 'Name 4', 'Name 5', 'Name 6'] as $field) {
            $part = self::lookup($row, $field);

            if ($part !== null) {
                $parts[] = $part;
            }
        }

        return $parts === [] ? null : implode(' ', $parts);
    }

    public static function isPrimaryName(?string $type): bool
    {
        return mb_strtolower(mb_trim((string) $type)) === 'primary name';
    }

    public static function isAlias(?string $type): bool
    {
        $normalized = mb_strtolower(mb_trim((string) $type));

        return $normalized === 'alias' || $normalized === 'primary name variation';
    }

    /**
     * @param  array<string, mixed>  $row
     * @return list<string>
     */
    public static function aliases(array $row): array
    {
        $aliases = $row['aliases'] ?? null;

        if (is_array($aliases)) {
            return self::strings($aliases);
        }

        if (self::isAlias(self::nameType($row))) {
            $name = self::assembledName($row);

            return $name === null ? [] : [$name];
        }

        return [];
    }

    /**
     * @param  array<string, mixed>  $row
     * @return list<string>
     */
    public static function nationalities(array $row): array
    {
        return self::list($row, 'nationalities', 'Nationality(/ies)');
    }

    /**
     * @param  array<string, mixed>  $row
     * @return list<string>
     */
    public static function phones(array $row): array
    {
        return self::list($row, 'phones', 'Phone number');
    }

    /**
     * @param  array<string, mixed>  $row
     * @return list<string>
     */
    public static function emails(array $row): array
    {
        return self::list($row, 'emails', 'Email address');
    }

    /**
     * @param  array<string, mixed>  $row
     * @return list<string>
     */
    public static function websites(array $row): array
    {
        return self::list($row, 'websites', 'Website');
    }

    /**
     * @param  array<string, mixed>  $row
     * @return list<string>
     */
    public static function nationalIds(array $row): array
    {
        return self::list($row, 'nationalIds', 'National Identifier number');
    }

    /**
     * @param  array<string, mixed>  $row
     * @return list<string>
     */
    public static function passports(array $row): array
    {
        return self::list($row, 'passports', 'Passport number');
    }

    /**
     * @param  array<string, mixed>  $row
     * @return list<string>
     */
    public static function registrationNumbers(array $row): array
    {
        return self::list($row, 'registrationNumbers', 'Business registration number (s)');
    }

    /**
     * @param  array<string, mixed>  $row
     * @return list<string>
     */
    public static function addresses(array $row): array
    {
        $addresses = $row['addresses'] ?? null;

        if (is_array($addresses)) {
            return self::strings($addresses);
        }

        $address = self::composeAddress($row);

        return $address === null ? [] : [$address];
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public static function composeAddress(array $row): ?string
    {
        $parts = [];

        foreach ([
            'Address Line 1',
            'Address Line 2',
            'Address Line 3',
            'Address Line 4',
            'Address Line 5',
            'Address Line 6',
            'Address Postal Code',
            'Address Country',
        ] as $field) {
            $part = self::lookup($row, $field);

            if ($part !== null) {
                $parts[] = $part;
            }
        }

        return $parts === [] ? null : implode(', ', $parts);
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array<string, mixed>
     */
    public static function mergeRows(array $rows): array
    {
        $uniqueId = null;
        $ofsiGroupId = null;
        $type = null;
        $name = null;
        $regime = null;
        $sanctionsImposed = null;
        $dateOfBirth = null;
        $position = null;
        $gender = null;
        $imo = null;
        $aliases = [];
        $nationalities = [];
        $phones = [];
        $emails = [];
        $websites = [];
        $nationalIds = [];
        $passports = [];
        $registrationNumbers = [];
        $addresses = [];

        foreach ($rows as $row) {
            $uniqueId ??= self::uniqueId($row);
            $ofsiGroupId ??= self::ofsiGroupId($row);
            $type ??= self::type($row);
            $regime ??= self::regime($row);
            $sanctionsImposed ??= self::sanctionsImposed($row);
            $dateOfBirth ??= self::dateOfBirth($row);
            $position ??= self::position($row);
            $gender ??= self::gender($row);
            $imo ??= self::imo($row);
            $nationalities = [...$nationalities, ...self::nationalities($row)];
            $phones = [...$phones, ...self::phones($row)];
            $emails = [...$emails, ...self::emails($row)];
            $websites = [...$websites, ...self::websites($row)];
            $nationalIds = [...$nationalIds, ...self::nationalIds($row)];
            $passports = [...$passports, ...self::passports($row)];
            $registrationNumbers = [...$registrationNumbers, ...self::registrationNumbers($row)];
            $addresses = [...$addresses, ...self::addresses($row)];

            $assembled = self::assembledName($row);
            $nameType = self::nameType($row);

            if ($assembled === null) {
                continue;
            }

            if (self::isPrimaryName($nameType) || ($name === null && ! self::isAlias($nameType))) {
                $name ??= $assembled;

                continue;
            }

            if ($assembled !== $name) {
                $aliases[] = $assembled;
            }
        }

        return [
            'uniqueId' => $uniqueId,
            'ofsiGroupId' => $ofsiGroupId,
            'type' => $type,
            'name' => $name,
            'aliases' => self::unique($aliases),
            'regime' => $regime,
            'sanctionsImposed' => $sanctionsImposed,
            'dateOfBirth' => $dateOfBirth,
            'position' => $position,
            'gender' => $gender,
            'imo' => $imo,
            'nationalities' => self::unique($nationalities),
            'phones' => self::unique($phones),
            'emails' => self::unique($emails),
            'websites' => self::unique($websites),
            'nationalIds' => self::unique($nationalIds),
            'passports' => self::unique($passports),
            'registrationNumbers' => self::unique($registrationNumbers),
            'addresses' => self::unique($addresses),
        ];
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
     * @return list<string>
     */
    private static function list(array $row, string $canonical, string $csv): array
    {
        $value = $row[$canonical] ?? null;

        if (is_array($value)) {
            return self::strings($value);
        }

        $raw = self::lookup($row, $csv);

        return $raw === null ? [] : self::split($raw);
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
            if (self::headerKey($header) === $wanted) {
                return self::string($value);
            }
        }

        return null;
    }

    private static function headerKey(string $header): string
    {
        return (string) preg_replace('/[^a-z0-9]+/', '', mb_strtolower(mb_trim($header)));
    }

    /**
     * @return list<string>
     */
    private static function split(string $value): array
    {
        $parts = preg_split('/[|;]+/', $value) ?: [];

        return self::strings($parts);
    }

    /**
     * @param  array<mixed>  $values
     * @return list<string>
     */
    private static function strings(array $values): array
    {
        $strings = [];

        foreach ($values as $value) {
            $string = self::string($value);

            if ($string !== null) {
                $strings[] = $string;
            }
        }

        return $strings;
    }

    /**
     * @param  list<string>  $values
     * @return list<string>
     */
    private static function unique(array $values): array
    {
        return array_values(array_unique($values));
    }

    private static function string(mixed $value): ?string
    {
        if (! is_string($value) && ! is_int($value) && ! is_float($value)) {
            return null;
        }

        $string = mb_trim((string) $value, " \t\n\r\0\x0B'");

        return $string === '' ? null : $string;
    }
}
