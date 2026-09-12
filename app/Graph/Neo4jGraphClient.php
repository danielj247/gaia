<?php

declare(strict_types=1);

namespace App\Graph;

use App\Enums\GraphEdgeType;
use App\Enums\GraphNodeLabel;
use Laudis\Neo4j\ParameterHelper;

final readonly class Neo4jGraphClient implements GraphClient
{
    public function __construct(private GraphSession $session) {}

    public function ensureSchema(): void
    {
        foreach (GraphNodeLabel::cases() as $label) {
            $name = $label->value;
            $this->session->run(
                sprintf('CREATE CONSTRAINT %s_id IF NOT EXISTS FOR (n:%s) REQUIRE n.id IS UNIQUE', mb_strtolower($name), $name),
            );
        }
    }

    /**
     * @param  array<string, bool|float|int|string|null>  $properties
     */
    public function mergeNode(GraphNodeLabel $label, string $id, array $properties): void
    {
        $this->session->run(
            sprintf('MERGE (n:%s {id: $id}) SET n += $props', $label->value),
            [
                'id' => $id,
                'props' => ParameterHelper::asMap([...$properties, 'id' => $id]),
            ],
        );
    }

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
    ): void {
        $fromSelector = $fromLabel === GraphNodeLabel::Other ? 'a' : 'a:'.$fromLabel->value;
        $toSelector = $toLabel === GraphNodeLabel::Other ? 'b' : 'b:'.$toLabel->value;

        $this->session->run(
            sprintf(
                'MATCH (%s {id: $fromId}), (%s {id: $toId}) MERGE (a)-[r:%s]->(b) SET r += $props',
                $fromSelector,
                $toSelector,
                $type->value,
            ),
            [
                'fromId' => $fromId,
                'toId' => $toId,
                'props' => ParameterHelper::asMap($properties),
            ],
        );
    }

    public function neighborhood(string $id, int $hops, int $limit): Neighborhood
    {
        $hops = max(1, min($hops, 3));
        $limit = max(1, min($limit, 1000));

        $rows = $this->session->run(
            sprintf(
                'MATCH (start {id: $id})
                OPTIONAL MATCH (start)-[*1..%d]-(seen)
                WITH collect(DISTINCT start) + [n IN collect(DISTINCT seen) WHERE n IS NOT NULL] AS raw
                WITH raw[..$limit] AS nodes, size(raw) > $limit AS truncated
                UNWIND nodes AS a
                OPTIONAL MATCH (a)-[r]-(b)
                WHERE b IN nodes
                RETURN a, r, b, startNode(r).id AS source, endNode(r).id AS target, type(r) AS relType, truncated',
                $hops,
            ),
            [
                'id' => $id,
                'limit' => $limit,
            ],
        );

        return $this->neighborhoodFromRows($rows);
    }

    /**
     * @return list<array{id: string, label: string, caption: string}>
     */
    public function search(string $query, int $limit): array
    {
        $needle = mb_trim($query);

        if ($needle === '') {
            return [];
        }

        $rows = $this->session->run(
            'MATCH (n)
            WHERE toLower(coalesce(n.caption, n.name, n.value, n.id, "")) CONTAINS toLower($query)
               OR toLower(n.id) CONTAINS toLower($query)
            RETURN n
            LIMIT $limit',
            [
                'query' => $needle,
                'limit' => $limit,
            ],
        );

        $hits = [];

        foreach ($rows as $row) {
            $node = $this->nodeFromValue($row['n'] ?? null);

            if ($node === null) {
                continue;
            }

            $hits[] = [
                'id' => $node['id'],
                'label' => $node['label'],
                'caption' => $node['caption'],
            ];
        }

        return $hits;
    }

    /**
     * @return array{nodes: int, edges: int}
     */
    public function stats(): array
    {
        $nodes = $this->session->run('MATCH (n) RETURN count(n) AS count');
        $edges = $this->session->run('MATCH ()-[r]->() RETURN count(r) AS count');

        return [
            'nodes' => $this->intValue($nodes[0]['count'] ?? 0),
            'edges' => $this->intValue($edges[0]['count'] ?? 0),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function neighborhoodFromRows(array $rows): Neighborhood
    {
        $nodes = [];
        $edges = [];
        $truncated = false;

        foreach ($rows as $row) {
            $truncated = $truncated || $row['truncated'] === true;

            foreach (['a', 'b'] as $key) {
                $node = $this->nodeFromValue($row[$key] ?? null);

                if ($node === null) {
                    continue;
                }

                $nodes[$node['id']] = $node;
            }

            $edge = $this->edgeFromValue($row['r'] ?? null, $row);

            if ($edge !== null) {
                $edges[$edge['id']] = $edge;
            }
        }

        return new Neighborhood(array_values($nodes), array_values($edges), $truncated);
    }

    /**
     * @return array{id: string, label: string, caption: string, properties: array<string, mixed>}|null
     */
    private function nodeFromValue(mixed $value): ?array
    {
        if (! is_array($value)) {
            return null;
        }

        $properties = $this->stringKeyed(
            isset($value['properties']) && is_array($value['properties'])
                ? $value['properties']
                : $value,
        );

        $id = $properties['id'] ?? null;

        if (! is_string($id) || $id === '') {
            return null;
        }

        $labels = $value['labels'] ?? [];
        $label = is_array($labels) && isset($labels[0]) && is_string($labels[0])
            ? $labels[0]
            : 'Other';

        $caption = $properties['caption'] ?? $properties['name'] ?? $properties['value'] ?? $id;

        return [
            'id' => $id,
            'label' => $label,
            'caption' => is_string($caption) ? $caption : $id,
            'properties' => $properties,
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array{id: string, type: string, source: string, target: string, properties: array<string, mixed>}|null
     */
    private function edgeFromValue(mixed $value, array $row = []): ?array
    {
        $type = is_string($row['relType'] ?? null) ? $row['relType'] : null;
        $fromId = is_string($row['source'] ?? null) ? $row['source'] : null;
        $toId = is_string($row['target'] ?? null) ? $row['target'] : null;
        $properties = [];

        if (is_array($value)) {
            $type ??= is_string($value['type'] ?? null) ? $value['type'] : null;
            $fromId ??= is_string($value['source'] ?? $value['start'] ?? null) ? ($value['source'] ?? $value['start']) : null;
            $toId ??= is_string($value['end'] ?? $value['target'] ?? null) ? ($value['end'] ?? $value['target']) : null;
            $properties = is_array($value['properties'] ?? null)
                ? $this->stringKeyed($value['properties'])
                : [];
        }

        if (! is_string($type) || ! is_string($fromId) || ! is_string($toId)) {
            return null;
        }

        return [
            'id' => $type.'|'.$fromId.'|'.$toId,
            'type' => $type,
            'source' => $fromId,
            'target' => $toId,
            'properties' => $properties,
        ];
    }

    private function intValue(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }

    /**
     * @param  array<mixed, mixed>  $value
     * @return array<string, mixed>
     */
    private function stringKeyed(array $value): array
    {
        $properties = [];

        foreach ($value as $key => $item) {
            if (is_string($key)) {
                $properties[$key] = $item;
            }
        }

        return $properties;
    }
}
