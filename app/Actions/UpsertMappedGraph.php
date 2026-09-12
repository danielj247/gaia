<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\GraphEdgeType;
use App\Enums\GraphNodeLabel;
use App\Graph\GraphClient;

final readonly class UpsertMappedGraph
{
    public function __construct(private GraphClient $graph) {}

    /**
     * @param  array{nodes: list<array{label: GraphNodeLabel, id: string, properties: array<string, bool|float|int|string|null>}>, edges: list<array{type: GraphEdgeType, fromLabel: GraphNodeLabel, fromId: string, toLabel: GraphNodeLabel, toId: string, properties: array<string, bool|float|int|string|null>}>}  $mapped
     * @return array{nodes: int, edges: int}
     */
    public function handle(array $mapped): array
    {
        foreach ($mapped['nodes'] as $node) {
            $this->graph->mergeNode($node['label'], $node['id'], $node['properties']);
        }

        foreach ($mapped['edges'] as $edge) {
            $this->graph->mergeEdge(
                $edge['type'],
                $edge['fromLabel'],
                $edge['fromId'],
                $edge['toLabel'],
                $edge['toId'],
                $edge['properties'],
            );
        }

        return [
            'nodes' => count($mapped['nodes']),
            'edges' => count($mapped['edges']),
        ];
    }
}
