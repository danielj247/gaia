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

    public function neighborhood(string $id, int $hops, int $limit): Neighborhood;

    /**
     * @return list<array{id: string, label: string, caption: string}>
     */
    public function search(string $query, int $limit): array;

    /**
     * @return array{nodes: int, edges: int}
     */
    public function stats(): array;
}
