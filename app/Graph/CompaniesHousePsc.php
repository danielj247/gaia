<?php

declare(strict_types=1);

namespace App\Graph;

final readonly class CompaniesHousePsc
{
    public const string KIND_INDIVIDUAL = 'individual-person-with-significant-control';

    public const string KIND_CORPORATE = 'corporate-entity-person-with-significant-control';

    public const string KIND_LEGAL = 'legal-person-person-with-significant-control';

    public const string KIND_SUPER_SECURE = 'super-secure-person-with-significant-control';

    public const string KIND_STATEMENT = 'persons-with-significant-control-statement';

    public const string KIND_TOTALS = 'totals#persons-of-significant-control-snapshot';

    /**
     * @param  array<string, mixed>  $record
     * @return array<string, mixed>
     */
    public static function payload(array $record): array
    {
        $data = $record['data'] ?? null;

        if (! is_array($data)) {
            return $record;
        }

        $payload = [];

        foreach ($data as $key => $value) {
            if (is_string($key)) {
                $payload[$key] = $value;
            }
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $record
     */
    public static function kind(array $record): ?string
    {
        $payload = self::payload($record);
        $kind = $payload['kind'] ?? $record['kind'] ?? null;

        return is_string($kind) && $kind !== '' ? $kind : null;
    }

    public static function isGraphKind(?string $kind): bool
    {
        return $kind === self::KIND_INDIVIDUAL
            || $kind === self::KIND_CORPORATE
            || $kind === self::KIND_LEGAL;
    }

    /**
     * @param  array<string, mixed>  $record
     */
    public static function companyNumber(array $record): ?string
    {
        return CompaniesHouseFields::companyNumber($record)
            ?? CompaniesHouseFields::companyNumber(self::payload($record));
    }

    /**
     * @param  array<string, mixed>  $record
     */
    public static function sourceId(array $record): ?string
    {
        $explicit = $record['id'] ?? null;

        if (is_string($explicit) && str_starts_with($explicit, 'ch-psc:')) {
            return $explicit;
        }

        $notificationId = self::notificationId(self::payload($record));

        return $notificationId === null ? null : 'ch-psc:'.$notificationId;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function notificationId(array $payload): ?string
    {
        $links = $payload['links'] ?? null;
        $self = is_array($links) ? ($links['self'] ?? null) : null;

        if (is_string($self) && mb_trim($self) !== '') {
            $segment = basename(mb_trim($self, '/'));

            if ($segment !== '') {
                return $segment;
            }
        }

        $etag = $payload['etag'] ?? null;

        return is_string($etag) && mb_trim($etag) !== '' ? mb_trim($etag) : null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function name(array $payload): ?string
    {
        $name = $payload['name'] ?? null;

        if (is_string($name) && mb_trim($name) !== '') {
            return mb_trim($name);
        }

        $elements = $payload['name_elements'] ?? null;

        if (! is_array($elements)) {
            return null;
        }

        $parts = [];

        foreach (['title', 'forename', 'middle_name', 'surname'] as $key) {
            $value = $elements[$key] ?? null;

            if (is_string($value) && mb_trim($value) !== '') {
                $parts[] = mb_trim($value);
            }
        }

        return $parts === [] ? null : implode(' ', $parts);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function birthDate(array $payload): ?string
    {
        $dob = $payload['date_of_birth'] ?? null;

        if (! is_array($dob)) {
            return is_string($payload['birthDate'] ?? null) ? mb_trim((string) $payload['birthDate']) : null;
        }

        $year = $dob['year'] ?? null;
        $month = $dob['month'] ?? null;

        if (! is_numeric($year) || ! is_numeric($month)) {
            return null;
        }

        return sprintf('%04d-%02d', (int) $year, (int) $month);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function serviceAddress(array $payload): ?string
    {
        $address = $payload['address'] ?? null;

        if (is_string($address) && mb_trim($address) !== '') {
            return mb_trim($address);
        }

        if (! is_array($address)) {
            return null;
        }

        $lines = [];

        foreach ([
            'care_of',
            'po_box',
            'premises',
            'address_line_1',
            'address_line_2',
            'locality',
            'region',
            'country',
            'postal_code',
        ] as $key) {
            $value = $address[$key] ?? null;

            if (is_string($value) && mb_trim($value) !== '' && ! in_array(mb_trim($value), $lines, true)) {
                $lines[] = mb_trim($value);
            }
        }

        return $lines === [] ? null : implode(', ', $lines);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function naturesOfControl(array $payload): ?string
    {
        $natures = $payload['natures_of_control'] ?? $payload['naturesOfControl'] ?? null;

        if (is_string($natures) && mb_trim($natures) !== '') {
            return mb_trim($natures);
        }

        if (! is_array($natures)) {
            return null;
        }

        $values = [];

        foreach ($natures as $nature) {
            if (is_string($nature) && mb_trim($nature) !== '') {
                $values[] = mb_trim($nature);
            }
        }

        return $values === [] ? null : implode(',', $values);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function registrationNumber(array $payload): ?string
    {
        $identification = $payload['identification'] ?? null;
        $value = is_array($identification) ? ($identification['registration_number'] ?? null) : null;

        if (! is_string($value) && ! is_int($value)) {
            return null;
        }

        $string = mb_trim((string) $value);

        return $string === '' ? null : $string;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function nationality(array $payload): ?string
    {
        $value = $payload['nationality'] ?? null;

        return is_string($value) && mb_trim($value) !== '' ? mb_trim($value) : null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function country(array $payload, string $field): ?string
    {
        $value = $payload[$field] ?? null;

        if (is_string($value) && mb_trim($value) !== '') {
            return mb_trim($value);
        }

        $identification = $payload['identification'] ?? null;
        $registered = is_array($identification) ? ($identification['country_registered'] ?? null) : null;

        return $field === 'country_registered' && is_string($registered) && mb_trim($registered) !== ''
            ? mb_trim($registered)
            : null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function notifiedOn(array $payload): ?string
    {
        $value = $payload['notified_on'] ?? $payload['startDate'] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function ceasedOn(array $payload): ?string
    {
        $value = $payload['ceased_on'] ?? $payload['endDate'] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }
}
