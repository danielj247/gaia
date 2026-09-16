<?php

declare(strict_types=1);

namespace App\Graph;

use App\Enums\GraphEdgeType;
use App\Enums\GraphNodeLabel;

interface GraphClient
{
    public function ensureSchema(): void;

    /**
     * @param  array<string, bool|float|int|string|null>  $properties
     */
    public function mergeNode(GraphNodeLabel $label, string $id, array $properties): void;

    /**
     * @param  array<string, bool|float|int|string|null>  $properties
     */
    public function mergeEdge(
        GraphEdgeType $type,
        GraphNodeLabel $fromLabel,
        string $fromId,
        GraphNodeLabel $toLabel,
        string $toId,
        array $properties = [],
    ): void;

    /**
     * Merge every node and then every edge of one mapped graph. Adapters may batch
     * the writes; the result must equal calling mergeNode/mergeEdge in list order.
     *
     * @param  array{nodes: list<array{label: GraphNodeLabel, id: string, properties: array<string, bool|float|int|string|null>}>, edges: list<array{type: GraphEdgeType, fromLabel: GraphNodeLabel, fromId: string, toLabel: GraphNodeLabel, toId: string, properties: array<string, bool|float|int|string|null>}>}  $mapped
     */
    public function mergeGraph(array $mapped): void;

    public function neighborhood(string $id, int $hops, int $limit): Neighborhood;

    /**
     * @return list<array{id: string, label: string, caption: string}>
     */
    public function search(string $query, int $limit): array;

    /**
     * @return array{nodes: int, edges: int}
     */
    public function stats(): array;

    public function findPersonId(string $sourceId): ?string;
}
