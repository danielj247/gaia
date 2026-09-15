<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\GraphEdgeType;
use App\Enums\GraphNodeLabel;
use App\Enums\IdentifierKind;
use App\Graph\IdentifierId;

final readonly class MapOfficialDesignation
{
    /**
     * @param  array{
     *     sourceId: string,
     *     list: string,
     *     label: GraphNodeLabel,
     *     name: string,
     *     aliases?: list<string>,
     *     birthDate?: string|null,
     *     position?: string|null,
     *     gender?: string|null,
     *     programs?: list<string>,
     *     sanctionPrefix?: string,
     *     sanctionsImposed?: string|null,
     *     identifiers?: list<array{kind: IdentifierKind, value: string}>,
     *     nationalities?: list<string>,
     *     addresses?: list<string>
     * }  $record
     * @return array{nodes: list<array{label: GraphNodeLabel, id: string, properties: array<string, bool|float|int|string|null>}>, edges: list<array{type: GraphEdgeType, fromLabel: GraphNodeLabel, fromId: string, toLabel: GraphNodeLabel, toId: string, properties: array<string, bool|float|int|string|null>}>}
     */
    public function handle(array $record, string $dumpId): array
    {
        $label = $record['label'];
        $id = $record['sourceId'];
        $name = $record['name'];
        $aliases = array_values(array_filter(
            $record['aliases'] ?? [],
            fn (string $alias): bool => $alias !== $name,
        ));
        $programs = $record['programs'] ?? [];
        $prefix = $record['sanctionPrefix'] ?? $record['list'];

        $nodes = [[
            'label' => $label,
            'id' => $id,
            'properties' => [
                'id' => $id,
                'sourceId' => $id,
                'caption' => $name,
                'name' => $name,
                'aliases' => $aliases === [] ? null : implode(',', $aliases),
                'birthDate' => $record['birthDate'] ?? null,
                'position' => $record['position'] ?? null,
                'gender' => $record['gender'] ?? null,
                'schema' => $label->value,
            ],
        ]];

        $edges = [[
            'type' => GraphEdgeType::AppearsInDump,
            'fromLabel' => $label,
            'fromId' => $id,
            'toLabel' => GraphNodeLabel::Dump,
            'toId' => $dumpId,
            'properties' => ['list' => $record['list']],
        ]];

        foreach ($record['identifiers'] ?? [] as $identifier) {
            $identifierId = IdentifierId::for($identifier['kind'], $identifier['value']);
            $nodes[] = [
                'label' => GraphNodeLabel::Identifier,
                'id' => $identifierId,
                'properties' => [
                    'id' => $identifierId,
                    'kind' => $identifier['kind']->value,
                    'value' => $identifier['value'],
                    'caption' => $identifier['value'],
                ],
            ];
            $edges[] = [
                'type' => GraphEdgeType::HasIdentifier,
                'fromLabel' => $label,
                'fromId' => $id,
                'toLabel' => GraphNodeLabel::Identifier,
                'toId' => $identifierId,
                'properties' => ['kind' => $identifier['kind']->value],
            ];
        }

        foreach ($record['nationalities'] ?? [] as $nationality) {
            $countryId = 'country:'.mb_strtolower($nationality);
            $nodes[] = [
                'label' => GraphNodeLabel::Country,
                'id' => $countryId,
                'properties' => [
                    'id' => $countryId,
                    'caption' => $nationality,
                    'code' => $nationality,
                ],
            ];
            $edges[] = [
                'type' => GraphEdgeType::CitizenOf,
                'fromLabel' => $label,
                'fromId' => $id,
                'toLabel' => GraphNodeLabel::Country,
                'toId' => $countryId,
                'properties' => ['field' => 'nationality'],
            ];
        }

        foreach ($record['addresses'] ?? [] as $address) {
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

        if ($programs === []) {
            $programs[] = $record['sourceId'];
        }

        foreach ($programs as $program) {
            $sanctionId = 'sanction:'.$prefix.':'.hash('sha256', mb_strtolower($program));
            $nodes[] = [
                'label' => GraphNodeLabel::Sanction,
                'id' => $sanctionId,
                'properties' => [
                    'id' => $sanctionId,
                    'caption' => $program,
                    'program' => $program,
                    'sanctionsImposed' => $record['sanctionsImposed'] ?? null,
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

        if ($aliases !== []) {
            $edges[] = [
                'type' => GraphEdgeType::AlsoKnownAs,
                'fromLabel' => $label,
                'fromId' => $id,
                'toLabel' => $label,
                'toId' => $id,
                'properties' => ['alias' => implode(',', $aliases)],
            ];
        }

        return ['nodes' => $nodes, 'edges' => $edges];
    }
}
