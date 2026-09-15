<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\GraphEdgeType;
use App\Enums\GraphNodeLabel;

final readonly class MapOfficialRecord
{
    public function __construct(
        private MapUkSanctionsRow $mapUk,
        private MapOfacSdnEntry $mapOfac,
        private MapEuFsfEntity $mapEu,
    ) {}

    /**
     * @param  array<string, mixed>  $entity
     * @return array{nodes: list<array{label: GraphNodeLabel, id: string, properties: array<string, bool|float|int|string|null>}>, edges: list<array{type: GraphEdgeType, fromLabel: GraphNodeLabel, fromId: string, toLabel: GraphNodeLabel, toId: string, properties: array<string, bool|float|int|string|null>}>}
     */
    public function handle(string $dataset, array $entity, string $dumpId): array
    {
        return match ($dataset) {
            'uksl' => $this->mapUk->handle($entity, $dumpId),
            'ofac_sdn' => $this->mapOfac->handle($entity, $dumpId),
            'eu_fsf' => $this->mapEu->handle($entity, $dumpId),
            default => ['nodes' => [], 'edges' => []],
        };
    }
}
