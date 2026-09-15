<?php

declare(strict_types=1);

namespace App\Actions;

use RuntimeException;
use SimpleXMLElement;
use Throwable;
use XMLReader;

final readonly class ConvertOfacSdnXml
{
    public function handle(string $xmlPath, string $jsonlPath): void
    {
        if (! is_file($xmlPath) || ! is_readable($xmlPath)) {
            throw new RuntimeException('Unable to read the OFAC SDN XML.');
        }

        $this->assertLooksLikeXml($xmlPath, 'Unable to read the OFAC SDN XML.');

        $reader = new XMLReader();
        $reader->open($xmlPath);

        if (is_dir($jsonlPath) || ! is_writable(dirname($jsonlPath))) {
            $reader->close();

            throw new RuntimeException('Unable to write the OFAC SDN JSON lines.');
        }

        $out = $this->openWrite($jsonlPath, 'Unable to write the OFAC SDN JSON lines.');

        try {
            while ($reader->read()) {
                if ($reader->nodeType !== XMLReader::ELEMENT || $reader->localName !== 'sdnEntry') {
                    continue;
                }

                do {
                    $record = $this->entryToRecord($reader->readOuterXML());
                    fwrite($out, json_encode($record ?? ['list' => 'ofac_sdn'], JSON_THROW_ON_ERROR)."\n");
                } while ($reader->next('sdnEntry'));

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
    private function entryToRecord(string $outerXml): ?array
    {
        $element = new SimpleXMLElement($outerXml);
        $node = $this->children($element);
        $uid = mb_trim((string) $node->uid);

        if ($uid === '') {
            return null;
        }

        $first = mb_trim((string) $node->firstName);
        $last = mb_trim((string) $node->lastName);
        $name = mb_trim($first.' '.$last);

        $passports = [];
        $nationalIds = [];
        $registrationNumbers = [];
        $emails = [];
        $phones = [];
        $websites = [];
        $imo = null;

        foreach ($this->repeating($node, 'idList', 'id') as $id) {
            $number = mb_trim((string) $id->idNumber);
            $type = mb_strtolower(mb_trim((string) $id->idType));

            if ($number === '') {
                continue;
            }

            if (str_contains($type, 'passport')) {
                $passports[] = $number;
            } elseif (str_contains($type, 'imo') || str_contains($type, 'vessel registration')) {
                $imo = $number;
            } elseif (str_contains($type, 'email')) {
                $emails[] = $number;
            } elseif (str_contains($type, 'website') || str_contains($type, 'url')) {
                $websites[] = $number;
            } elseif (str_contains($type, 'phone') || str_contains($type, 'telephone') || str_contains($type, 'fax')) {
                $phones[] = $number;
            } elseif (str_contains($type, 'national') || str_contains($type, 'cedula') || $type === 'ssn') {
                $nationalIds[] = $number;
            } elseif (str_contains($type, 'registration') || str_contains($type, 'fein')) {
                $registrationNumbers[] = $number;
            }
        }

        $aliases = [];

        foreach ($this->repeating($node, 'akaList', 'aka') as $aka) {
            $alias = mb_trim(mb_trim((string) $aka->firstName).' '.mb_trim((string) $aka->lastName));

            if ($alias !== '') {
                $aliases[] = $alias;
            }
        }

        $addresses = [];

        foreach ($this->repeating($node, 'addressList', 'address') as $address) {
            $parts = array_values(array_filter([
                mb_trim((string) $address->address1),
                mb_trim((string) $address->address2),
                mb_trim((string) $address->address3),
                mb_trim((string) $address->city),
                mb_trim((string) $address->stateOrProvince),
                mb_trim((string) $address->postalCode),
                mb_trim((string) $address->country),
            ], fn (string $part): bool => $part !== ''));

            if ($parts !== []) {
                $addresses[] = implode(', ', $parts);
            }
        }

        $nationalities = [];

        foreach ($this->repeating($node, 'nationalityList', 'nationality') as $nationality) {
            $country = mb_trim((string) $nationality->country);

            if ($country !== '') {
                $nationalities[] = $country;
            }
        }

        $dateOfBirth = null;

        foreach ($this->repeating($node, 'dateOfBirthList', 'dateOfBirthItem') as $item) {
            $value = mb_trim((string) $item->dateOfBirth);

            if ($value !== '') {
                $dateOfBirth = $value;

                break;
            }
        }

        $programs = [];

        foreach ($this->repeating($node, 'programList', 'program') as $program) {
            $value = mb_trim((string) $program);

            if ($value !== '') {
                $programs[] = $value;
            }
        }

        return [
            'id' => 'ofac:'.$uid,
            'list' => 'ofac_sdn',
            'uid' => $uid,
            'type' => mb_trim((string) $node->sdnType),
            'name' => $name === '' ? $uid : $name,
            'aliases' => $aliases,
            'title' => mb_trim((string) $node->title) ?: null,
            'programs' => $programs,
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

    private function children(SimpleXMLElement $element): SimpleXMLElement
    {
        $namespace = $element->getNamespaces(true)[''] ?? null;

        if (is_string($namespace) && $namespace !== '') {
            return $element->children($namespace);
        }

        return $element->children();
    }

    /**
     * @return list<SimpleXMLElement>
     */
    private function repeating(SimpleXMLElement $node, string $list, string $item): array
    {
        if (! isset($node->{$list}) || ! isset($node->{$list}->{$item})) {
            return [];
        }

        $children = $node->{$list}->{$item};
        $items = [];

        foreach ($children instanceof SimpleXMLElement ? $children : [] as $child) {
            $items[] = $child;
        }

        return $items;
    }
}
