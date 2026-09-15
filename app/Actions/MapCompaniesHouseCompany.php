<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\GraphEdgeType;
use App\Enums\GraphNodeLabel;
use App\Enums\IdentifierKind;
use App\Graph\CompaniesHouseFields;
use App\Graph\IdentifierId;

/**
 * Maps one Free Company Data Product row onto Gaia's locked node/edge shape.
 *
 * Organization.sourceId is the zero-padded company_number. Previous names stay
 * on the organization. This file has no officers and must not create Person nodes.
 */
final readonly class MapCompaniesHouseCompany
{
    /**
     * @param  array<string, mixed>  $row
     * @return array{nodes: list<array{label: GraphNodeLabel, id: string, properties: array<string, bool|float|int|string|null>}>, edges: list<array{type: GraphEdgeType, fromLabel: GraphNodeLabel, fromId: string, toLabel: GraphNodeLabel, toId: string, properties: array<string, bool|float|int|string|null>}>}
     */
    public function handle(array $row, string $dumpId): array
    {
        $companyNumber = CompaniesHouseFields::companyNumber($row);

        if ($companyNumber === null) {
            return ['nodes' => [], 'edges' => []];
        }

        $name = CompaniesHouseFields::name($row) ?? $companyNumber;
        $address = CompaniesHouseFields::registeredOffice($row);
        $country = CompaniesHouseFields::countryOfOrigin($row);
        $identifierId = IdentifierId::for(IdentifierKind::CompanyNumber, $companyNumber);

        $nodes = [[
            'label' => GraphNodeLabel::Organization,
            'id' => $companyNumber,
            'properties' => [
                'id' => $companyNumber,
                'sourceId' => $companyNumber,
                'caption' => $name,
                'name' => $name,
                'schema' => GraphNodeLabel::Organization->value,
                'companyNumber' => $companyNumber,
                'companyStatus' => CompaniesHouseFields::companyStatus($row),
                'companyCategory' => CompaniesHouseFields::companyCategory($row),
                'countryOfOrigin' => $country,
                'incorporationDate' => CompaniesHouseFields::incorporationDate($row),
                'dissolutionDate' => CompaniesHouseFields::dissolutionDate($row),
                'uri' => CompaniesHouseFields::uri($row),
                'sicCodes' => CompaniesHouseFields::sicCodes($row),
                'previousNames' => CompaniesHouseFields::previousNames($row),
            ],
        ], [
            'label' => GraphNodeLabel::Identifier,
            'id' => $identifierId,
            'properties' => [
                'id' => $identifierId,
                'kind' => IdentifierKind::CompanyNumber->value,
                'value' => $companyNumber,
                'caption' => $companyNumber,
            ],
        ]];

        $edges = [[
            'type' => GraphEdgeType::AppearsInDump,
            'fromLabel' => GraphNodeLabel::Organization,
            'fromId' => $companyNumber,
            'toLabel' => GraphNodeLabel::Dump,
            'toId' => $dumpId,
            'properties' => ['list' => 'ch_companies'],
        ], [
            'type' => GraphEdgeType::HasIdentifier,
            'fromLabel' => GraphNodeLabel::Organization,
            'fromId' => $companyNumber,
            'toLabel' => GraphNodeLabel::Identifier,
            'toId' => $identifierId,
            'properties' => ['kind' => IdentifierKind::CompanyNumber->value],
        ]];

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
                'fromLabel' => GraphNodeLabel::Organization,
                'fromId' => $companyNumber,
                'toLabel' => GraphNodeLabel::Address,
                'toId' => $addressId,
                'properties' => [],
            ];
        }

        if ($country !== null) {
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
                'type' => GraphEdgeType::LocatedAt,
                'fromLabel' => GraphNodeLabel::Organization,
                'fromId' => $companyNumber,
                'toLabel' => GraphNodeLabel::Country,
                'toId' => $countryId,
                'properties' => ['field' => 'country'],
            ];
        }

        return ['nodes' => $nodes, 'edges' => $edges];
    }
}
