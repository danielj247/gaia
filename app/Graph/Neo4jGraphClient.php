<?php

declare(strict_types=1);

namespace App\Graph;

use App\Enums\GraphEdgeType;
use App\Enums\GraphNodeLabel;
use Laudis\Neo4j\ParameterHelper;
use Laudis\Neo4j\Types\CypherMap;

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

        $this->session->run(
            sprintf(
                'CREATE INDEX person_source_id IF NOT EXISTS FOR (n:%s) ON (n.sourceId)',
                GraphNodeLabel::Person->value,
            ),
        );
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
        if ($fromLabel === GraphNodeLabel::Other && $toLabel === GraphNodeLabel::Other) {
            $this->session->run(
                sprintf(
                    'MERGE (a {id: $fromId})
                    ON CREATE SET a:Other, a.id = $fromId, a.caption = $fromId
                    MERGE (b {id: $toId})
                    ON CREATE SET b:Other, b.id = $toId, b.caption = $toId
                    MERGE (a)-[r:%s]->(b)
                    SET r += $props',
                    $type->value,
                ),
                [
                    'fromId' => $fromId,
                    'toId' => $toId,
                    'props' => ParameterHelper::asMap($properties),
                ],
            );

            return;
        }

        $fromSelector = 'a:'.$fromLabel->value;
        $toSelector = 'b:'.$toLabel->value;

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

    /**
     * One UNWIND statement per node label, then one per (type, fromLabel, toLabel)
     * edge group, so a chunk costs a handful of Bolt round-trips instead of one per
     * row. Rows are deduplicated and sorted by id so concurrent workers take locks
     * on shared hub nodes (Dump, Country, Address) in the same order.
     *
     * @param  array{nodes: list<array{label: GraphNodeLabel, id: string, properties: array<string, bool|float|int|string|null>}>, edges: list<array{type: GraphEdgeType, fromLabel: GraphNodeLabel, fromId: string, toLabel: GraphNodeLabel, toId: string, properties: array<string, bool|float|int|string|null>}>}  $mapped
     */
    public function mergeGraph(array $mapped): void
    {
        foreach ($this->nodeRows($mapped['nodes']) as $label => $rows) {
            $this->session->run(
                sprintf('UNWIND $rows AS row MERGE (n:%s {id: row.id}) SET n += row.props', $label),
                ['rows' => $rows],
            );
        }

        foreach ($this->edgeRows($mapped['edges']) as $group) {
            $this->session->run(
                $this->edgeBatchStatement($group['type'], $group['fromLabel'], $group['toLabel']),
                ['rows' => $group['rows']],
            );
        }
    }

    public function neighborhood(string $id, int $hops, int $limit): Neighborhood
    {
        $hops = max(1, min($hops, 3));
        $limit = max(1, min($limit, 1000));

        $rows = $this->session->run(
            sprintf(
                'MATCH (start {id: $id})
                OPTIONAL MATCH path = (start)-[*1..%d]-(candidate)
                WHERE NOT (start:Dump OR start:Country OR start:Sanction)
                  AND ALL(n IN nodes(path) WHERE n = start OR NOT (n:Dump OR n:Country OR n:Sanction))
                WITH start, collect(DISTINCT CASE WHEN candidate IS NULL OR candidate:Dump OR candidate:Country OR candidate:Sanction THEN NULL ELSE candidate END) AS seen
                WITH start, [n IN seen WHERE n IS NOT NULL] AS neighbors
                WITH [start] + neighbors AS raw
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
            WHERE NOT (n:Dump OR n:Country OR n:Sanction)
              AND (
                toLower(coalesce(n.caption, n.name, n.value, n.id, "")) CONTAINS toLower($query)
                OR toLower(coalesce(n.aliases, "")) CONTAINS toLower($query)
                OR toLower(coalesce(n.sourceId, "")) CONTAINS toLower($query)
                OR toLower(n.id) CONTAINS toLower($query)
              )
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

    public function findPersonId(string $sourceId): ?string
    {
        $needle = mb_trim($sourceId);

        if ($needle === '') {
            return null;
        }

        $rows = $this->session->run(
            'MATCH (p:Person)
            WHERE p.sourceId = $sourceId OR p.id = $sourceId
            RETURN p.id AS id
            LIMIT 1',
            ['sourceId' => $needle],
        );

        $id = $rows[0]['id'] ?? null;

        return is_string($id) && $id !== '' ? $id : null;
    }

    /**
     * @param  list<array{label: GraphNodeLabel, id: string, properties: array<string, bool|float|int|string|null>}>  $nodes
     * @return array<string, list<array{id: string, props: CypherMap<bool|float|int|string|null>}>>
     */
    private function nodeRows(array $nodes): array
    {
        $grouped = [];

        foreach ($nodes as $node) {
            $label = $node['label']->value;
            $existing = $grouped[$label][$node['id']]['properties'] ?? [];
            $grouped[$label][$node['id']] = [
                'id' => $node['id'],
                'properties' => [...$existing, ...$node['properties'], 'id' => $node['id']],
            ];
        }

        $rows = [];

        foreach ($grouped as $label => $byId) {
            ksort($byId, SORT_STRING);

            foreach ($byId as $node) {
                $rows[$label][] = [
                    'id' => $node['id'],
                    'props' => ParameterHelper::asMap($node['properties']),
                ];
            }
        }

        return $rows;
    }

    /**
     * @param  list<array{type: GraphEdgeType, fromLabel: GraphNodeLabel, fromId: string, toLabel: GraphNodeLabel, toId: string, properties: array<string, bool|float|int|string|null>}>  $edges
     * @return list<array{type: GraphEdgeType, fromLabel: GraphNodeLabel, toLabel: GraphNodeLabel, rows: list<array{fromId: string, toId: string, props: CypherMap<bool|float|int|string|null>}>}>
     */
    private function edgeRows(array $edges): array
    {
        $groups = [];

        foreach ($edges as $edge) {
            $key = $edge['type']->value.'|'.$edge['fromLabel']->value.'|'.$edge['toLabel']->value;
            $groups[$key] ??= [
                'type' => $edge['type'],
                'fromLabel' => $edge['fromLabel'],
                'toLabel' => $edge['toLabel'],
                'byEnd' => [],
            ];
            $existing = $groups[$key]['byEnd'][$edge['toId']][$edge['fromId']]['properties'] ?? [];
            $groups[$key]['byEnd'][$edge['toId']][$edge['fromId']] = [
                'fromId' => $edge['fromId'],
                'toId' => $edge['toId'],
                'properties' => [...$existing, ...$edge['properties']],
            ];
        }

        $batches = [];

        foreach ($groups as $group) {
            $rows = [];
            ksort($group['byEnd'], SORT_STRING);

            foreach ($group['byEnd'] as $byStart) {
                ksort($byStart, SORT_STRING);

                foreach ($byStart as $edge) {
                    $rows[] = [
                        'fromId' => $edge['fromId'],
                        'toId' => $edge['toId'],
                        'props' => ParameterHelper::asMap($edge['properties']),
                    ];
                }
            }

            $batches[] = [
                'type' => $group['type'],
                'fromLabel' => $group['fromLabel'],
                'toLabel' => $group['toLabel'],
                'rows' => $rows,
            ];
        }

        return $batches;
    }

    private function edgeBatchStatement(GraphEdgeType $type, GraphNodeLabel $fromLabel, GraphNodeLabel $toLabel): string
    {
        if ($fromLabel === GraphNodeLabel::Other && $toLabel === GraphNodeLabel::Other) {
            return sprintf(
                'UNWIND $rows AS row
                MERGE (a {id: row.fromId})
                ON CREATE SET a:Other, a.id = row.fromId, a.caption = row.fromId
                MERGE (b {id: row.toId})
                ON CREATE SET b:Other, b.id = row.toId, b.caption = row.toId
                MERGE (a)-[r:%s]->(b)
                SET r += row.props',
                $type->value,
            );
        }

        return sprintf(
            'UNWIND $rows AS row MATCH (a:%s {id: row.fromId}), (b:%s {id: row.toId}) MERGE (a)-[r:%s]->(b) SET r += row.props',
            $fromLabel->value,
            $toLabel->value,
            $type->value,
        );
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
            $truncated = $truncated || $this->isTruthy($row['truncated'] ?? false);

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
        $type = $this->stringId($row['relType'] ?? null);
        $fromId = $this->stringId($row['source'] ?? null) ?? $this->nodeApplicationId($row['a'] ?? null);
        $toId = $this->stringId($row['target'] ?? null) ?? $this->nodeApplicationId($row['b'] ?? null);
        $properties = [];

        if (is_array($value)) {
            $type ??= $this->stringId($value['type'] ?? null);
            $fromId ??= $this->stringId($value['source'] ?? null);
            $toId ??= $this->stringId($value['target'] ?? null);
            $properties = is_array($value['properties'] ?? null)
                ? $this->stringKeyed($value['properties'])
                : [];
        }

        if ($type === null || $fromId === null || $toId === null) {
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

    private function stringId(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }

    private function nodeApplicationId(mixed $value): ?string
    {
        $node = $this->nodeFromValue($value);

        return $node === null ? null : $node['id'];
    }

    private function isTruthy(mixed $value): bool
    {
        return in_array($value, [true, 1, '1', 'true'], true);
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
