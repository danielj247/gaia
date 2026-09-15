<?php

declare(strict_types=1);

namespace App\Actions;

use RuntimeException;
use SimpleXMLElement;
use Throwable;
use XMLReader;

final readonly class ConvertEuFsfXml
{
    public function handle(string $xmlPath, string $jsonlPath): void
    {
        if (! is_file($xmlPath) || ! is_readable($xmlPath)) {
            throw new RuntimeException('Unable to read the EU FSF XML.');
        }

        $this->assertLooksLikeXml($xmlPath, 'Unable to read the EU FSF XML.');

        $reader = new XMLReader();
        $reader->open($xmlPath);

        if (is_dir($jsonlPath) || ! is_writable(dirname($jsonlPath))) {
            $reader->close();

            throw new RuntimeException('Unable to write the EU FSF JSON lines.');
        }

        $out = $this->openWrite($jsonlPath, 'Unable to write the EU FSF JSON lines.');

        try {
            while ($reader->read()) {
                if ($reader->nodeType !== XMLReader::ELEMENT || $reader->localName !== 'sanctionEntity') {
                    continue;
                }

                do {
                    $record = $this->entityToRecord($reader->readOuterXML());
                    fwrite($out, json_encode($record ?? ['list' => 'eu_fsf'], JSON_THROW_ON_ERROR)."\n");
                } while ($reader->next('sanctionEntity'));

                break;
            }
        } finally {
            $reader->close();
            fclose($out);
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private function entityToRecord(string $outerXml): ?array
    {
        $element = new SimpleXMLElement($outerXml);
        $logicalId = $this->attr($element, 'logicalId');
        $reference = $this->attr($element, 'euReferenceNumber');

        if ($logicalId === '' && $reference === '') {
            return null;
        }

        $subjects = $this->elements($element, 'subjectType');
        $subject = $subjects[0] ?? null;
        $type = $subject === null ? '' : $this->attr($subject, 'code');

        if ($type === '') {
            $type = $subject === null ? '' : $this->attr($subject, 'classificationCode');
        }

        $name = null;
        $gender = null;
        $function = null;
        $aliases = [];

        foreach ($this->elements($element, 'nameAlias') as $alias) {
            $whole = $this->attr($alias, 'wholeName');

            if ($whole === '') {
                $whole = mb_trim($this->attr($alias, 'firstName').' '.$this->attr($alias, 'lastName'));
            }

            if ($whole === '') {
                continue;
            }

            $strong = mb_strtolower($this->attr($alias, 'strong')) === 'true';

            if ($strong || $name === null) {
                $name ??= $whole;
                $gender ??= $this->attr($alias, 'gender') ?: null;
                $function ??= $this->attr($alias, 'function') ?: null;

                if ($strong) {
                    $name = $whole;
                }

                continue;
            }

            $aliases[] = $whole;
        }

        $programs = [];

        foreach ($this->elements($element, 'regulation') as $regulation) {
            $programme = $this->attr($regulation, 'programme');

            if ($programme !== '') {
                $programs[] = $programme;
            }
        }

        $nationalities = [];

        foreach ($this->elements($element, 'citizenship') as $citizenship) {
            $code = $this->attr($citizenship, 'countryIso2Code');
            $country = $code !== '' ? $code : $this->attr($citizenship, 'countryDescription');

            if ($country !== '') {
                $nationalities[] = $country;
            }
        }

        $dateOfBirth = null;

        foreach ($this->elements($element, 'birthdate') as $birthdate) {
            $value = $this->attr($birthdate, 'birthdate');

            if ($value === '') {
                $parts = array_values(array_filter([
                    $this->attr($birthdate, 'year'),
                    $this->attr($birthdate, 'monthOfYear'),
                    $this->attr($birthdate, 'dayOfMonth'),
                ], fn (string $part): bool => $part !== ''));
                $value = implode('-', $parts);
            }

            if ($value !== '') {
                $dateOfBirth = $value;

                break;
            }
        }

        $addresses = [];
        $phones = [];
        $emails = [];
        $websites = [];

        foreach ($this->elements($element, 'address') as $address) {
            $parts = array_values(array_filter([
                $this->attr($address, 'street'),
                $this->attr($address, 'poBox'),
                $this->attr($address, 'city'),
                $this->attr($address, 'zipCode'),
                $this->attr($address, 'region'),
                $this->attr($address, 'place'),
                $this->attr($address, 'countryDescription') ?: $this->attr($address, 'countryIso2Code'),
            ], fn (string $part): bool => $part !== ''));

            if ($parts !== []) {
                $addresses[] = implode(', ', $parts);
            }

            $this->collectContacts($address, $phones, $emails, $websites);
        }

        $this->collectContacts($element, $phones, $emails, $websites);

        $passports = [];
        $nationalIds = [];
        $registrationNumbers = [];
        $imo = null;

        foreach ($this->elements($element, 'identification') as $identification) {
            $number = $this->attr($identification, 'number');
            $idType = mb_strtolower($this->attr($identification, 'identificationTypeCode').' '.$this->attr($identification, 'identificationTypeDescription'));

            if ($number === '') {
                continue;
            }

            if (str_contains($idType, 'passport')) {
                $passports[] = $number;
            } elseif (str_contains($idType, 'imo')) {
                $imo = $number;
            } elseif (str_contains($idType, 'registration')) {
                $registrationNumbers[] = $number;
            } else {
                $nationalIds[] = $number;
            }
        }

        $id = $logicalId !== '' ? $logicalId : $reference;

        return [
            'id' => 'eu:'.$id,
            'list' => 'eu_fsf',
            'logicalId' => $logicalId !== '' ? $logicalId : null,
            'euReferenceNumber' => $reference !== '' ? $reference : null,
            'type' => $type,
            'name' => $name ?? $id,
            'aliases' => $aliases,
            'gender' => $gender,
            'function' => $function,
            'programs' => array_values(array_unique($programs)),
            'dateOfBirth' => $dateOfBirth,
            'passports' => $passports,
            'nationalIds' => $nationalIds,
            'registrationNumbers' => $registrationNumbers,
            'emails' => $emails,
            'phones' => $phones,
            'websites' => $websites,
            'imo' => $imo,
            'nationalities' => $nationalities,
            'addresses' => $addresses,
        ];
    }

    /**
     * @return list<SimpleXMLElement>
     */
    private function elements(SimpleXMLElement $element, string $name): array
    {
        $namespaces = $element->getNamespaces(true);
        $uri = $namespaces[''] ?? null;

        if (is_string($uri) && $uri !== '') {
            $element->registerXPathNamespace('e', $uri);
            $found = $element->xpath('.//e:'.$name);

            return is_array($found) ? array_values($found) : [];
        }

        $found = $element->xpath('.//'.$name);

        return is_array($found) ? array_values($found) : [];
    }

    private function assertLooksLikeXml(string $path, string $message): void
    {
        $prefix = file_get_contents($path, false, null, 0, 256) ?: '';

        if (! str_contains($prefix, '<')) {
            throw new RuntimeException($message);
        }
    }

    /**
     * @return resource
     */
    private function openWrite(string $path, string $message)
    {
        try {
            $handle = fopen($path, 'w');
        } catch (Throwable) {
            $handle = false;
        }

        if ($handle === false) {
            throw new RuntimeException($message);
        }

        return $handle;
    }

    private function attr(SimpleXMLElement $element, string $name): string
    {
        $attributes = $element->attributes();

        if ($attributes !== null && isset($attributes[$name])) {
            return mb_trim((string) $attributes[$name]);
        }

        foreach ($element->getNamespaces(true) as $uri) {
            $namespaced = $element->attributes($uri);

            if ($namespaced !== null && isset($namespaced[$name])) {
                return mb_trim((string) $namespaced[$name]);
            }
        }

        return mb_trim((string) $element[$name]);
    }

    /**
     * @param  list<string>  $phones
     * @param  list<string>  $emails
     * @param  list<string>  $websites
     */
    private function collectContacts(SimpleXMLElement $node, array &$phones, array &$emails, array &$websites): void
    {
        foreach ($this->elements($node, 'contactInfo') as $contact) {
            $key = mb_strtolower($this->attr($contact, 'key'));
            $value = $this->attr($contact, 'value');

            if ($value === '') {
                continue;
            }

            if (str_contains($key, 'phone') || str_contains($key, 'tel') || str_contains($key, 'fax')) {
                $phones[] = $value;
            } elseif (str_contains($key, 'mail')) {
                $emails[] = $value;
            } elseif (str_contains($key, 'web') || str_contains($key, 'url')) {
                $websites[] = $value;
            }
        }
    }
}
