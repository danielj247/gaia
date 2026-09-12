<?php

declare(strict_types=1);

namespace App\Data;

use Spatie\LaravelData\Data;

final class NeighborhoodData extends Data
{
    /**
     * @param  list<GraphNodeData>  $nodes
     * @param  list<GraphEdgeData>  $edges
     */
    public function __construct(
        public readonly array $nodes,
        public readonly array $edges,
        public readonly bool $truncated,
    ) {}
}
