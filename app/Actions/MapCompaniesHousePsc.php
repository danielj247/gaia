<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\GraphEdgeType;
use App\Enums\GraphNodeLabel;
use App\Enums\IdentifierKind;
use App\Graph\CompaniesHousePsc;
use App\Graph\IdentifierId;

/**
 * Maps one Companies House PSC snapshot line onto Gaia's locked node/edge shape.
 *
 * Person sourceId is the notification id (or etag), never the name. Super-secure
 * and statement rows are not graph-visible people.
 */
final readonly class MapCompaniesHousePsc
{
    /**
     * @param  array<string, mixed>  $record
     * @return array{nodes: list<array{label: GraphNodeLabel, id: string, properties: array<string, bool|float|int|string|null>}>, edges: list<array{type: GraphEdgeType, fromLabel: GraphNodeLabel, fromId: string, toLabel: GraphNodeLabel, toId: string, properties: array<string, bool|float|int|string|null>}>}
     */
    public function handle(array $record, string $dumpId): array
    {
        $kind = CompaniesHousePsc::kind($record);

        if (! CompaniesHousePsc::isGraphKind($kind)) {
            return ['nodes' => [], 'edges' => []];
        }

        $companyNumber = CompaniesHousePsc::companyNumber($record);
        $sourceId = CompaniesHousePsc::sourceId($record);
        $payload = CompaniesHousePsc::payload($record);

        if ($companyNumber === null || $sourceId === null) {
            return ['nodes' => [], 'edges' => []];
        }

        $ownerLabel = $kind === CompaniesHousePsc::KIND_INDIVIDUAL
            ? GraphNodeLabel::Person
            : GraphNodeLabel::Organization;
        $name = CompaniesHousePsc::name($payload) ?? $sourceId;
        $address = CompaniesHousePsc::serviceAddress($payload);
        $companyIdentifierId = IdentifierId::for(IdentifierKind::CompanyNumber, $companyNumber);
        $identification = $payload['identification'] ?? null;
        $identification = is_array($identification) ? $identification : [];

        $nodes = [[
            'label' => $ownerLabel,
            'id' => $sourceId,
            'properties' => [
                'id' => $sourceId,
                'sourceId' => $sourceId,
                'caption' => $name,
                'name' => $name,
                'schema' => $ownerLabel->value,
                'birthDate' => $ownerLabel === GraphNodeLabel::Person ? CompaniesHousePsc::birthDate($payload) : null,
                'legalForm' => $this->string($identification['legal_form'] ?? null),
                'legalAuthority' => $this->string($identification['legal_authority'] ?? null),
            ],
        ], [
            'label' => GraphNodeLabel::Organization,
            'id' => $companyNumber,
            'properties' => [
                'id' => $companyNumber,
                'sourceId' => $companyNumber,
                'caption' => $companyNumber,
                'name' => $companyNumber,
                'schema' => GraphNodeLabel::Organization->value,
                'companyNumber' => $companyNumber,
            ],
        ], [
            'label' => GraphNodeLabel::Identifier,
            'id' => $companyIdentifierId,
            'properties' => [
                'id' => $companyIdentifierId,
                'kind' => IdentifierKind::CompanyNumber->value,
                'value' => $companyNumber,
                'caption' => $companyNumber,
            ],
        ]];

        $edges = [[
            'type' => GraphEdgeType::AppearsInDump,
            'fromLabel' => $ownerLabel,
            'fromId' => $sourceId,
            'toLabel' => GraphNodeLabel::Dump,
            'toId' => $dumpId,
            'properties' => ['list' => 'ch_psc'],
        ], [
            'type' => GraphEdgeType::AppearsInDump,
            'fromLabel' => GraphNodeLabel::Organization,
            'fromId' => $companyNumber,
            'toLabel' => GraphNodeLabel::Dump,
            'toId' => $dumpId,
            'properties' => ['list' => 'ch_psc'],
        ], [
            'type' => GraphEdgeType::HasIdentifier,
            'fromLabel' => GraphNodeLabel::Organization,
            'fromId' => $companyNumber,
            'toLabel' => GraphNodeLabel::Identifier,
            'toId' => $companyIdentifierId,
            'properties' => ['kind' => IdentifierKind::CompanyNumber->value],
        ], [
            'type' => GraphEdgeType::Owns,
            'fromLabel' => $ownerLabel,
            'fromId' => $sourceId,
            'toLabel' => GraphNodeLabel::Organization,
            'toId' => $companyNumber,
            'properties' => [
                'naturesOfControl' => CompaniesHousePsc::naturesOfControl($payload),
                'startDate' => CompaniesHousePsc::notifiedOn($payload),
                'endDate' => CompaniesHousePsc::ceasedOn($payload),
            ],
        ]];

        $registrationNumber = CompaniesHousePsc::registrationNumber($payload);

        if ($registrationNumber !== null) {
            $registrationId = IdentifierId::for(IdentifierKind::RegistrationNumber, $registrationNumber);
            $nodes[] = [
                'label' => GraphNodeLabel::Identifier,
                'id' => $registrationId,
                'properties' => [
                    'id' => $registrationId,
                    'kind' => IdentifierKind::RegistrationNumber->value,
                    'value' => $registrationNumber,
                    'caption' => $registrationNumber,
                ],
            ];
            $edges[] = [
                'type' => GraphEdgeType::HasIdentifier,
                'fromLabel' => $ownerLabel,
                'fromId' => $sourceId,
                'toLabel' => GraphNodeLabel::Identifier,
                'toId' => $registrationId,
                'properties' => ['kind' => IdentifierKind::RegistrationNumber->value],
            ];
        }

        if ($address !== null) {
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
                'fromLabel' => $ownerLabel,
                'fromId' => $sourceId,
                'toLabel' => GraphNodeLabel::Address,
                'toId' => $addressId,
                'properties' => [],
            ];
        }

        $nationality = CompaniesHousePsc::nationality($payload);

        if ($nationality !== null) {
            $this->attachCountry($nodes, $edges, $ownerLabel, $sourceId, $nationality, GraphEdgeType::CitizenOf, 'nationality');
        }

        foreach (['country_of_residence', 'country_registered'] as $field) {
            $country = CompaniesHousePsc::country($payload, $field);

            if ($country !== null) {
                $this->attachCountry($nodes, $edges, $ownerLabel, $sourceId, $country, GraphEdgeType::LocatedAt, $field);
            }
        }

        return ['nodes' => $nodes, 'edges' => $edges];
    }

    /**
     * @param  list<array{label: GraphNodeLabel, id: string, properties: array<string, bool|float|int|string|null>}>  $nodes
     * @param  list<array{type: GraphEdgeType, fromLabel: GraphNodeLabel, fromId: string, toLabel: GraphNodeLabel, toId: string, properties: array<string, bool|float|int|string|null>}>  $edges
     */
    private function attachCountry(
        array &$nodes,
        array &$edges,
        GraphNodeLabel $fromLabel,
        string $fromId,
        string $country,
        GraphEdgeType $type,
        string $field,
    ): void {
        $countryId = 'country:'.mb_strtolower($country);
        $nodes[] = [
            'label' => GraphNodeLabel::Country,
            'id' => $countryId,
            'properties' => [
                'id' => $countryId,
                'caption' => $country,
                'code' => $country,
            ],
        ];
        $edges[] = [
            'type' => $type,
            'fromLabel' => $fromLabel,
            'fromId' => $fromId,
            'toLabel' => GraphNodeLabel::Country,
            'toId' => $countryId,
            'properties' => ['field' => $field],
        ];
    }

    private function string(mixed $value): ?string
    {
        return is_string($value) && mb_trim($value) !== '' ? mb_trim($value) : null;
    }
}
