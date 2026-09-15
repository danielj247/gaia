<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\GraphEdgeType;
use App\Enums\GraphNodeLabel;
use App\Graph\GraphClient;
use App\Models\PersonKey;

final readonly class AssignPersonIds
{
    public function __construct(
        private ResolvePersonId $resolve,
        private GraphClient $graph,
    ) {}

    /**
     * @param  array{nodes: list<array{label: GraphNodeLabel, id: string, properties: array<string, bool|float|int|string|null>}>, edges: list<array{type: GraphEdgeType, fromLabel: GraphNodeLabel, fromId: string, toLabel: GraphNodeLabel, toId: string, properties: array<string, bool|float|int|string|null>}>}  $mapped
     * @return array{nodes: list<array{label: GraphNodeLabel, id: string, properties: array<string, bool|float|int|string|null>}>, edges: list<array{type: GraphEdgeType, fromLabel: GraphNodeLabel, fromId: string, toLabel: GraphNodeLabel, toId: string, properties: array<string, bool|float|int|string|null>}>}
     */
    public function handle(array $mapped): array
    {
        $nodes = [];

        foreach ($mapped['nodes'] as $node) {
            if ($node['label'] !== GraphNodeLabel::Person) {
                $nodes[] = $node;

                continue;
            }

            $sourceId = $this->sourceId($node);
            $gaiaId = $this->resolve->handle($sourceId);

            $nodes[] = [
                'label' => $node['label'],
                'id' => $gaiaId,
                'properties' => [
                    ...$node['properties'],
                    'id' => $gaiaId,
                    'sourceId' => $sourceId,
                ],
            ];
        }

        $edges = [];

        foreach ($mapped['edges'] as $edge) {
            $edges[] = [
                ...$edge,
                'fromId' => $this->remap($edge['fromId'], $edge['fromLabel']),
                'toId' => $this->remap($edge['toId'], $edge['toLabel']),
            ];
        }

        return [
            'nodes' => $nodes,
            'edges' => $edges,
        ];
    }

    /**
     * @param  array{label: GraphNodeLabel, id: string, properties: array<string, bool|float|int|string|null>}  $node
     */
    private function sourceId(array $node): string
    {
        $sourceId = $node['properties']['sourceId'] ?? $node['id'];

        return is_string($sourceId) && $sourceId !== '' ? $sourceId : $node['id'];
    }

    private function remap(string $id, GraphNodeLabel $label): string
    {
        if ($label === GraphNodeLabel::Person) {
            return $this->resolve->handle($id);
        }

        $mapped = PersonKey::query()->where('source_id', $id)->value('gaia_id');

        if (is_string($mapped) && $mapped !== '') {
            return $mapped;
        }

        return $this->graph->findPersonId($id) ?? $id;
    }
}
