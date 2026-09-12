<?php

declare(strict_types=1);

namespace App\Actions;

use App\Data\GraphEdgeData;
use App\Data\GraphNodeData;
use App\Data\NeighborhoodData;
use App\Graph\GraphClient;

final readonly class ExpandNeighborhood
{
    public function __construct(private GraphClient $graph) {}

    public function handle(string $id, int $hops = 1, int $limit = 200): NeighborhoodData
    {
        $neighborhood = $this->graph->neighborhood($id, $hops, $limit);

        return new NeighborhoodData(
            nodes: array_map(
                fn (array $node): GraphNodeData => new GraphNodeData(
                    $node['id'],
                    $node['label'],
                    $node['caption'],
                    $node['properties'],
                ),
                $neighborhood->nodes,
            ),
            edges: array_map(
                fn (array $edge): GraphEdgeData => new GraphEdgeData(
                    $edge['id'],
                    $edge['type'],
                    $edge['source'],
                    $edge['target'],
                    $edge['properties'],
                ),
                $neighborhood->edges,
            ),
            truncated: $neighborhood->truncated,
        );
    }
}
