<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\GraphEdgeType;
use App\Enums\GraphNodeLabel;
use App\Enums\IdentifierKind;

/**
 * Maps one EU FSF sanctionEntity onto Gaia's locked node/edge shape.
 *
 * Sanction nodes are one per programme: `sanction:eu:{sha256(lowercase programme)}`.
 * euReferenceNumber is Identifier(eu_fsf_id). Person sourceId is `eu:{logicalId}`.
 */
final readonly class MapEuFsfEntity
{
    public function __construct(private MapOfficialDesignation $map) {}

    /**
     * @param  array<string, mixed>  $entity
     * @return array{nodes: list<array{label: GraphNodeLabel, id: string, properties: array<string, bool|float|int|string|null>}>, edges: list<array{type: GraphEdgeType, fromLabel: GraphNodeLabel, fromId: string, toLabel: GraphNodeLabel, toId: string, properties: array<string, bool|float|int|string|null>}>}
     */
    public function handle(array $entity, string $dumpId): array
    {
        $logicalId = $this->string($entity['logicalId'] ?? null);
        $reference = $this->string($entity['euReferenceNumber'] ?? null);
        $sourceId = $logicalId ?? $reference;

        if ($sourceId === null) {
            return ['nodes' => [], 'edges' => []];
        }

        $identifiers = [
            ['kind' => IdentifierKind::EuFsfId, 'value' => $reference ?? $sourceId],
        ];

        foreach ($this->strings($entity['passports'] ?? []) as $passport) {
            $identifiers[] = ['kind' => IdentifierKind::Passport, 'value' => $passport];
        }

        foreach ($this->strings($entity['nationalIds'] ?? []) as $nationalId) {
            $identifiers[] = ['kind' => IdentifierKind::NationalId, 'value' => $nationalId];
        }

        foreach ($this->strings($entity['registrationNumbers'] ?? []) as $registrationNumber) {
            $identifiers[] = ['kind' => IdentifierKind::RegistrationNumber, 'value' => $registrationNumber];
        }

        foreach ($this->strings($entity['emails'] ?? []) as $email) {
            $identifiers[] = ['kind' => IdentifierKind::Email, 'value' => $email];
        }

        foreach ($this->strings($entity['phones'] ?? []) as $phone) {
            $identifiers[] = ['kind' => IdentifierKind::Phone, 'value' => $phone];
        }

        foreach ($this->strings($entity['websites'] ?? []) as $website) {
            $identifiers[] = ['kind' => IdentifierKind::Website, 'value' => $website];
        }

        $imo = $this->string($entity['imo'] ?? null);

        if ($imo !== null) {
            $identifiers[] = ['kind' => IdentifierKind::Imo, 'value' => $imo];
        }

        return $this->map->handle([
            'sourceId' => 'eu:'.$sourceId,
            'list' => 'eu_fsf',
            'label' => $this->label($this->string($entity['type'] ?? null)),
            'name' => $this->string($entity['name'] ?? null) ?? $sourceId,
            'aliases' => $this->strings($entity['aliases'] ?? []),
            'birthDate' => $this->string($entity['dateOfBirth'] ?? null),
            'position' => $this->string($entity['function'] ?? null),
            'gender' => $this->string($entity['gender'] ?? null),
            'programs' => $this->strings($entity['programs'] ?? []),
            'sanctionPrefix' => 'eu',
            'identifiers' => $identifiers,
            'nationalities' => $this->strings($entity['nationalities'] ?? []),
            'addresses' => $this->strings($entity['addresses'] ?? []),
        ], $dumpId);
    }

    private function label(?string $type): GraphNodeLabel
    {
        return match (mb_strtolower(mb_trim((string) $type))) {
            'person', 'p' => GraphNodeLabel::Person,
            'ship', 'vessel' => GraphNodeLabel::Vessel,
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
