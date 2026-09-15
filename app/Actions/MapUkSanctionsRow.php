<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\GraphEdgeType;
use App\Enums\GraphNodeLabel;
use App\Enums\IdentifierKind;
use App\Graph\UkSanctionsFields;

/**
 * Maps one UK Sanctions List designation onto Gaia's locked node/edge shape.
 *
 * Sanction nodes are one per regime: `sanction:uksl:{sha256(lowercase Regime Name)}`.
 * Unique ID is Identifier(uksl_id). Person sourceId is `uksl:{Unique ID}`, never the name.
 */
final readonly class MapUkSanctionsRow
{
    public function __construct(private MapOfficialDesignation $map) {}

    /**
     * @param  array<string, mixed>  $row
     * @return array{nodes: list<array{label: GraphNodeLabel, id: string, properties: array<string, bool|float|int|string|null>}>, edges: list<array{type: GraphEdgeType, fromLabel: GraphNodeLabel, fromId: string, toLabel: GraphNodeLabel, toId: string, properties: array<string, bool|float|int|string|null>}>}
     */
    public function handle(array $row, string $dumpId): array
    {
        $uniqueId = UkSanctionsFields::uniqueId($row);

        if ($uniqueId === null) {
            return ['nodes' => [], 'edges' => []];
        }

        $identifiers = [
            ['kind' => IdentifierKind::UkslId, 'value' => $uniqueId],
        ];

        $ofsi = UkSanctionsFields::ofsiGroupId($row);

        if ($ofsi !== null) {
            $identifiers[] = ['kind' => IdentifierKind::OfsiGroupId, 'value' => $ofsi];
        }

        foreach (UkSanctionsFields::passports($row) as $passport) {
            $identifiers[] = ['kind' => IdentifierKind::Passport, 'value' => $passport];
        }

        foreach (UkSanctionsFields::nationalIds($row) as $nationalId) {
            $identifiers[] = ['kind' => IdentifierKind::NationalId, 'value' => $nationalId];
        }

        foreach (UkSanctionsFields::registrationNumbers($row) as $registrationNumber) {
            $identifiers[] = ['kind' => IdentifierKind::RegistrationNumber, 'value' => $registrationNumber];
        }

        foreach (UkSanctionsFields::emails($row) as $email) {
            $identifiers[] = ['kind' => IdentifierKind::Email, 'value' => $email];
        }

        foreach (UkSanctionsFields::phones($row) as $phone) {
            $identifiers[] = ['kind' => IdentifierKind::Phone, 'value' => $phone];
        }

        foreach (UkSanctionsFields::websites($row) as $website) {
            $identifiers[] = ['kind' => IdentifierKind::Website, 'value' => $website];
        }

        $imo = UkSanctionsFields::imo($row);

        if ($imo !== null) {
            $identifiers[] = ['kind' => IdentifierKind::Imo, 'value' => $imo];
        }

        $regime = UkSanctionsFields::regime($row);

        return $this->map->handle([
            'sourceId' => 'uksl:'.$uniqueId,
            'list' => 'uksl',
            'label' => $this->label(UkSanctionsFields::type($row)),
            'name' => UkSanctionsFields::assembledName($row) ?? $uniqueId,
            'aliases' => UkSanctionsFields::aliases($row),
            'birthDate' => UkSanctionsFields::dateOfBirth($row),
            'position' => UkSanctionsFields::position($row),
            'gender' => UkSanctionsFields::gender($row),
            'programs' => $regime === null ? [] : [$regime],
            'sanctionPrefix' => 'uksl',
            'sanctionsImposed' => UkSanctionsFields::sanctionsImposed($row),
            'identifiers' => $identifiers,
            'nationalities' => UkSanctionsFields::nationalities($row),
            'addresses' => UkSanctionsFields::addresses($row),
        ], $dumpId);
    }

    private function label(?string $type): GraphNodeLabel
    {
        return match (mb_strtolower(mb_trim((string) $type))) {
            'individual' => GraphNodeLabel::Person,
            'ship' => GraphNodeLabel::Vessel,
            default => GraphNodeLabel::Organization,
        };
    }
}
