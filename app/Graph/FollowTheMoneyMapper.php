<?php

declare(strict_types=1);

namespace App\Graph;

use App\Enums\GraphEdgeType;
use App\Enums\GraphNodeLabel;
use App\Enums\IdentifierKind;

final readonly class FollowTheMoneyMapper
{
    /**
     * @var list<string>
     */
    private const array INTERVAL_SCHEMAS = [
        'Ownership',
        'Directorship',
        'Occupancy',
        'Representation',
        'UnknownLink',
        'Family',
        'Membership',
        'Employment',
        'Associate',
        'Succession',
        'Documentation',
    ];

    /**
     * @param  array<string, mixed>  $entity
     */
    public function isInterval(array $entity): bool
    {
        $schema = $this->string($entity['schema'] ?? null);

        return $schema !== null && in_array($schema, self::INTERVAL_SCHEMAS, true);
    }

    /**
     * @param  array<string, mixed>  $entity
     * @return array{nodes: list<array{label: GraphNodeLabel, id: string, properties: array<string, bool|float|int|string|null>}>, edges: list<array{type: GraphEdgeType, fromLabel: GraphNodeLabel, fromId: string, toLabel: GraphNodeLabel, toId: string, properties: array<string, bool|float|int|string|null>}>}
     */
    public function map(array $entity, string $dumpId): array
    {
        $schema = $this->string($entity['schema'] ?? null) ?? 'Thing';
        $id = $this->string($entity['id'] ?? null);

        if ($id === null) {
            return ['nodes' => [], 'edges' => []];
        }

        if ($this->isInterval($entity)) {
            return $this->mapInterval($entity, $id, $schema, $dumpId);
        }

        return $this->mapEntity($entity, $id, $schema, $dumpId);
    }

    /**
     * @param  array<string, mixed>  $entity
     * @return array{nodes: list<array{label: GraphNodeLabel, id: string, properties: array<string, bool|float|int|string|null>}>, edges: list<array{type: GraphEdgeType, fromLabel: GraphNodeLabel, fromId: string, toLabel: GraphNodeLabel, toId: string, properties: array<string, bool|float|int|string|null>}>}
     */
    private function mapEntity(array $entity, string $id, string $schema, string $dumpId): array
    {
        $label = GraphNodeLabel::fromFollowTheMoney($schema);
        $properties = $this->properties($entity['properties'] ?? []);
        $caption = $this->string($entity['caption'] ?? null) ?? $this->first($properties, 'name') ?? $id;

        $nodes = [[
            'label' => $label,
            'id' => $id,
            'properties' => [
                'id' => $id,
                'sourceId' => $id,
                'caption' => $caption,
                'schema' => $schema,
                'name' => $this->first($properties, 'name') ?? $caption,
                'notes' => $this->first($properties, 'notes'),
                'sourceUrl' => $this->first($properties, 'sourceUrl'),
                'topics' => $this->join($properties['topics'] ?? []),
                'birthDate' => $this->first($properties, 'birthDate'),
                'incorporationDate' => $this->first($properties, 'incorporationDate'),
                'gender' => $this->first($properties, 'gender'),
                'aliases' => $this->join($properties['alias'] ?? []),
            ],
        ]];

        $edges = [[
            'type' => GraphEdgeType::AppearsInDump,
            'fromLabel' => $label,
            'fromId' => $id,
            'toLabel' => GraphNodeLabel::Dump,
            'toId' => $dumpId,
            'properties' => ['schema' => $schema],
        ]];

        $nodes[] = [
            'label' => GraphNodeLabel::Identifier,
            'id' => IdentifierId::for(IdentifierKind::OpenSanctionsId, $id),
            'properties' => [
                'id' => IdentifierId::for(IdentifierKind::OpenSanctionsId, $id),
                'kind' => IdentifierKind::OpenSanctionsId->value,
                'value' => $id,
                'caption' => $id,
            ],
        ];

        $edges[] = [
            'type' => GraphEdgeType::HasIdentifier,
            'fromLabel' => $label,
            'fromId' => $id,
            'toLabel' => GraphNodeLabel::Identifier,
            'toId' => IdentifierId::for(IdentifierKind::OpenSanctionsId, $id),
            'properties' => ['kind' => IdentifierKind::OpenSanctionsId->value],
        ];

        foreach (IdentifierKind::cases() as $kind) {
            foreach (IdentifierKind::followTheMoneyProperties($kind) as $field) {
                foreach ($properties[$field] ?? [] as $value) {
                    if (! is_string($value) || $value === '') {
                        continue;
                    }

                    $identifierId = IdentifierId::for($kind, $value);
                    $nodes[] = [
                        'label' => GraphNodeLabel::Identifier,
                        'id' => $identifierId,
                        'properties' => [
                            'id' => $identifierId,
                            'kind' => $kind->value,
                            'value' => $value,
                            'caption' => $value,
                        ],
                    ];
                    $edges[] = [
                        'type' => GraphEdgeType::HasIdentifier,
                        'fromLabel' => $label,
                        'fromId' => $id,
                        'toLabel' => GraphNodeLabel::Identifier,
                        'toId' => $identifierId,
                        'properties' => ['kind' => $kind->value, 'field' => $field],
                    ];
                }
            }
        }

        foreach (['nationality', 'country'] as $field) {
            foreach ($properties[$field] ?? [] as $code) {
                if (! is_string($code) || $code === '') {
                    continue;
                }

                $nodes[] = [
                    'label' => GraphNodeLabel::Country,
                    'id' => 'country:'.$code,
                    'properties' => [
                        'id' => 'country:'.$code,
                        'caption' => mb_strtoupper($code),
                        'code' => $code,
                    ],
                ];
                $edges[] = [
                    'type' => $field === 'nationality' ? GraphEdgeType::CitizenOf : GraphEdgeType::LocatedAt,
                    'fromLabel' => $label,
                    'fromId' => $id,
                    'toLabel' => GraphNodeLabel::Country,
                    'toId' => 'country:'.$code,
                    'properties' => ['field' => $field],
                ];
            }
        }

        foreach ($properties['address'] ?? [] as $address) {
            if (! is_string($address) || $address === '') {
                continue;
            }

            if ($this->looksLikeEntityId($address)) {
                $nodes[] = [
                    'label' => GraphNodeLabel::Address,
                    'id' => $address,
                    'properties' => [
                        'id' => $address,
                        'caption' => $address,
                    ],
                ];
                $edges[] = [
                    'type' => GraphEdgeType::LocatedAt,
                    'fromLabel' => $label,
                    'fromId' => $id,
                    'toLabel' => GraphNodeLabel::Address,
                    'toId' => $address,
                    'properties' => [],
                ];

                continue;
            }

            $addressId = 'addr:'.hash('sha256', mb_strtolower($address));
            $nodes[] = [
                'label' => GraphNodeLabel::Address,
                'id' => $addressId,
                'properties' => [
                    'id' => $addressId,
                    'caption' => $address,
                    'value' => $address,
                ],
            ];
            $edges[] = [
                'type' => GraphEdgeType::LocatedAt,
                'fromLabel' => $label,
                'fromId' => $id,
                'toLabel' => GraphNodeLabel::Address,
                'toId' => $addressId,
                'properties' => [],
            ];
        }

        if (str_contains($this->join($properties['topics'] ?? []) ?? '', 'sanction')) {
            $program = $this->first($properties, 'programId') ?? 'OFAC-SDN';
            $sanctionId = 'sanction:'.$program;
            $nodes[] = [
                'label' => GraphNodeLabel::Sanction,
                'id' => $sanctionId,
                'properties' => [
                    'id' => $sanctionId,
                    'caption' => $program,
                    'program' => $program,
                ],
            ];
            $edges[] = [
                'type' => GraphEdgeType::SanctionedUnder,
                'fromLabel' => $label,
                'fromId' => $id,
                'toLabel' => GraphNodeLabel::Sanction,
                'toId' => $sanctionId,
                'properties' => [],
            ];
        }

        return ['nodes' => $nodes, 'edges' => $edges];
    }

    /**
     * @param  array<string, mixed>  $entity
     * @return array{nodes: list<array{label: GraphNodeLabel, id: string, properties: array<string, bool|float|int|string|null>}>, edges: list<array{type: GraphEdgeType, fromLabel: GraphNodeLabel, fromId: string, toLabel: GraphNodeLabel, toId: string, properties: array<string, bool|float|int|string|null>}>}
     */
    private function mapInterval(array $entity, string $id, string $schema, string $dumpId): array
    {
        $properties = $this->properties($entity['properties'] ?? []);
        [$fromField, $toField, $type] = match ($schema) {
            'Ownership' => ['owner', 'asset', GraphEdgeType::Owns],
            'Directorship', 'Membership', 'Employment' => ['director', 'organization', GraphEdgeType::MemberOf],
            'Family' => ['person', 'personRelative', GraphEdgeType::FamilyOf],
            default => ['subject', 'object', GraphEdgeType::RelatedTo],
        };

        $fromIds = $this->entityRefs($properties, [$fromField, 'owner', 'director', 'member', 'person', 'holder', 'agent', 'subject']);
        $toIds = $this->entityRefs($properties, [$toField, 'asset', 'organization', 'object', 'client', 'personRelative']);

        $edges = [];

        foreach ($fromIds as $fromId) {
            foreach ($toIds as $toId) {
                $edges[] = [
                    'type' => $type,
                    'fromLabel' => GraphNodeLabel::Other,
                    'fromId' => $fromId,
                    'toLabel' => GraphNodeLabel::Other,
                    'toId' => $toId,
                    'properties' => [
                        'schema' => $schema,
                        'intervalId' => $id,
                        'dumpId' => $dumpId,
                    ],
                ];
            }
        }

        return ['nodes' => [], 'edges' => $edges];
    }

    /**
     * @param  array<string, list<mixed>>  $properties
     * @param  list<string>  $fields
     * @return list<string>
     */
    private function entityRefs(array $properties, array $fields): array
    {
        $ids = [];

        foreach ($fields as $field) {
            foreach ($properties[$field] ?? [] as $value) {
                $id = $this->entityRef($value);

                if ($id !== null) {
                    $ids[] = $id;
                }
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * @return array<string, list<mixed>>
     */
    private function properties(mixed $raw): array
    {
        if (! is_array($raw)) {
            return [];
        }

        $properties = [];

        foreach ($raw as $key => $value) {
            if (! is_string($key)) {
                continue;
            }

            $properties[$key] = is_array($value) ? array_values($value) : [$value];
        }

        return $properties;
    }

    /**
     * @param  array<string, list<mixed>>  $properties
     */
    private function first(array $properties, string $field): ?string
    {
        $value = $properties[$field][0] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * @param  list<mixed>  $values
     */
    private function join(array $values): ?string
    {
        $strings = array_values(array_filter(
            $values,
            fn (mixed $value): bool => is_string($value) && $value !== '',
        ));

        return $strings === [] ? null : implode(',', $strings);
    }

    private function string(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }

    private function entityRef(mixed $value): ?string
    {
        if (is_string($value) && $value !== '') {
            return $value;
        }

        if (! is_array($value)) {
            return null;
        }

        $id = $value['id'] ?? null;

        return is_string($id) && $id !== '' ? $id : null;
    }

    private function looksLikeEntityId(string $value): bool
    {
        return str_contains($value, '-') && ! str_contains($value, ' ');
    }
}
