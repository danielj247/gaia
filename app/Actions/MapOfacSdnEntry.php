<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\GraphEdgeType;
use App\Enums\GraphNodeLabel;
use App\Enums\IdentifierKind;

/**
 * Maps one OFAC SDN entry onto Gaia's locked node/edge shape.
 *
 * Sanction nodes are one per OFAC program: `sanction:ofac:{sha256(lowercase program)}`.
 * The SDN uid is Identifier(ofac_id). Person sourceId is `ofac:{uid}`, never the name.
 */
final readonly class MapOfacSdnEntry
{
    public function __construct(private MapOfficialDesignation $map) {}

    /**
     * @param  array<string, mixed>  $entry
     * @return array{nodes: list<array{label: GraphNodeLabel, id: string, properties: array<string, bool|float|int|string|null>}>, edges: list<array{type: GraphEdgeType, fromLabel: GraphNodeLabel, fromId: string, toLabel: GraphNodeLabel, toId: string, properties: array<string, bool|float|int|string|null>}>}
     */
    public function handle(array $entry, string $dumpId): array
    {
        $uid = $this->string($entry['uid'] ?? null);

        if ($uid === null) {
            return ['nodes' => [], 'edges' => []];
        }

        $identifiers = [
            ['kind' => IdentifierKind::OfacId, 'value' => $uid],
        ];

        foreach ($this->strings($entry['passports'] ?? []) as $passport) {
            $identifiers[] = ['kind' => IdentifierKind::Passport, 'value' => $passport];
        }

        foreach ($this->strings($entry['nationalIds'] ?? []) as $nationalId) {
            $identifiers[] = ['kind' => IdentifierKind::NationalId, 'value' => $nationalId];
        }

        foreach ($this->strings($entry['registrationNumbers'] ?? []) as $registrationNumber) {
            $identifiers[] = ['kind' => IdentifierKind::RegistrationNumber, 'value' => $registrationNumber];
        }

        foreach ($this->strings($entry['emails'] ?? []) as $email) {
            $identifiers[] = ['kind' => IdentifierKind::Email, 'value' => $email];
        }

        foreach ($this->strings($entry['phones'] ?? []) as $phone) {
            $identifiers[] = ['kind' => IdentifierKind::Phone, 'value' => $phone];
        }

        foreach ($this->strings($entry['websites'] ?? []) as $website) {
            $identifiers[] = ['kind' => IdentifierKind::Website, 'value' => $website];
        }

        $imo = $this->string($entry['imo'] ?? null);

        if ($imo !== null) {
            $identifiers[] = ['kind' => IdentifierKind::Imo, 'value' => $imo];
        }

        return $this->map->handle([
            'sourceId' => 'ofac:'.$uid,
            'list' => 'ofac_sdn',
            'label' => $this->label($this->string($entry['type'] ?? null)),
            'name' => $this->string($entry['name'] ?? null) ?? $uid,
            'aliases' => $this->strings($entry['aliases'] ?? []),
            'birthDate' => $this->string($entry['dateOfBirth'] ?? null),
            'position' => $this->string($entry['title'] ?? null),
            'programs' => $this->strings($entry['programs'] ?? []),
            'sanctionPrefix' => 'ofac',
            'identifiers' => $identifiers,
            'nationalities' => $this->strings($entry['nationalities'] ?? []),
            'addresses' => $this->strings($entry['addresses'] ?? []),
        ], $dumpId);
    }

    private function label(?string $type): GraphNodeLabel
    {
        return match (mb_strtolower(mb_trim((string) $type))) {
            'individual' => GraphNodeLabel::Person,
            'vessel' => GraphNodeLabel::Vessel,
            'aircraft' => GraphNodeLabel::Aircraft,
            default => GraphNodeLabel::Organization,
        };
    }

    /**
     * @return list<string>
     */
    private function strings(mixed $values): array
    {
        if (! is_array($values)) {
            $value = $this->string($values);

            return $value === null ? [] : [$value];
        }

        $strings = [];

        foreach ($values as $value) {
            $string = $this->string($value);

            if ($string !== null) {
                $strings[] = $string;
            }
        }

        return $strings;
    }

    private function string(mixed $value): ?string
    {
        return is_string($value) && mb_trim($value) !== '' ? mb_trim($value) : null;
    }
}
