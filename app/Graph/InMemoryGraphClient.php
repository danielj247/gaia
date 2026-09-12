<?php

declare(strict_types=1);

namespace App\Graph;

use App\Enums\GraphEdgeType;
use App\Enums\GraphNodeLabel;

final class InMemoryGraphClient implements GraphClient
{
    /**
     * @var array<string, array{id: string, label: string, properties: array<string, bool|float|int|string|null>}>
     */
    private array $nodes = [];

    /**
     * @var array<string, array{id: string, type: string, from: string, to: string, properties: array<string, bool|float|int|string|null>}>
     */
    private array $edges = [];

    public function ensureSchema(): void
    {
        //
    }

    /**
     * @param  array<string, bool|float|int|string|null>  $properties
     */
    public function mergeNode(GraphNodeLabel $label, string $id, array $properties): void
    {
        $key = $this->nodeKey($label, $id);
        $existing = $this->nodes[$key]['properties'] ?? [];

        $this->nodes[$key] = [
            'id' => $id,
            'label' => $label->value,
            'properties' => [...$existing, ...$properties, 'id' => $id],
        ];
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
        $from = $this->findKey($fromId, $fromLabel);
        $to = $this->findKey($toId, $toLabel);

        if ($from === null || $to === null) {
            return;
        }

        $key = implode('|', [$type->value, $from, $to]);
        $existing = $this->edges[$key]['properties'] ?? [];

        $this->edges[$key] = [
            'id' => $key,
            'type' => $type->value,
            'from' => $from,
            'to' => $to,
            'properties' => [...$existing, ...$properties],
        ];
    }

    public function neighborhood(string $id, int $hops, int $limit): Neighborhood
    {
        $hops = max(1, min($hops, 3));
        $limit = max(1, min($limit, 1000));

        $frontier = [];

        foreach ($this->nodes as $key => $node) {
            if ($node['id'] === $id) {
                $frontier[$key] = true;
            }
        }

        $visited = $frontier;

        for ($hop = 0; $hop < $hops; $hop++) {
            $next = [];

            foreach (array_keys($frontier) as $key) {
                foreach ($this->edges as $edge) {
                    if ($edge['from'] === $key && ! isset($visited[$edge['to']])) {
                        $next[$edge['to']] = true;
                    }

                    if ($edge['to'] === $key && ! isset($visited[$edge['from']])) {
                        $next[$edge['from']] = true;
                    }
                }
            }

            $visited = [...$visited, ...$next];
            $frontier = $next;
        }

        $nodeKeys = array_keys($visited);
        $truncated = count($nodeKeys) > $limit;
        $nodeKeys = array_slice($nodeKeys, 0, $limit);
        $allowed = array_fill_keys($nodeKeys, true);

        $nodes = [];

        foreach ($nodeKeys as $key) {
            $node = $this->nodes[$key];
            $nodes[] = [
                'id' => $node['id'],
                'label' => $node['label'],
                'caption' => $this->caption($node['properties'], $node['id']),
                'properties' => $node['properties'],
            ];
        }

        $edges = [];

        foreach ($this->edges as $edge) {
            if (! isset($allowed[$edge['from']], $allowed[$edge['to']])) {
                continue;
            }

            $edges[] = [
                'id' => $edge['id'],
                'type' => $edge['type'],
                'source' => $this->nodes[$edge['from']]['id'],
                'target' => $this->nodes[$edge['to']]['id'],
                'properties' => $edge['properties'],
            ];
        }

        return new Neighborhood($nodes, $edges, $truncated);
    }

    /**
     * @return list<array{id: string, label: string, caption: string}>
     */
    public function search(string $query, int $limit): array
    {
        $needle = mb_strtolower(mb_trim($query));

        if ($needle === '') {
            return [];
        }

        $hits = [];

        foreach ($this->nodes as $node) {
            $caption = $this->caption($node['properties'], $node['id']);
            $haystack = mb_strtolower($caption.' '.$node['id'].' '.$node['label']);

            if (! str_contains($haystack, $needle)) {
                continue;
            }

            $hits[] = [
                'id' => $node['id'],
                'label' => $node['label'],
                'caption' => $caption,
            ];

            if (count($hits) >= $limit) {
                break;
            }
        }

        return $hits;
    }

    /**
     * @return array{nodes: int, edges: int}
     */
    public function stats(): array
    {
        return [
            'nodes' => count($this->nodes),
            'edges' => count($this->edges),
        ];
    }

    private function nodeKey(GraphNodeLabel $label, string $id): string
    {
        return $label->value.':'.$id;
    }

    private function findKey(string $id, GraphNodeLabel $label): ?string
    {
        if ($label !== GraphNodeLabel::Other) {
            $key = $this->nodeKey($label, $id);

            return isset($this->nodes[$key]) ? $key : null;
        }

        foreach ($this->nodes as $key => $node) {
            if ($node['id'] === $id) {
                return $key;
            }
        }

        return null;
    }

    /**
     * @param  array<string, bool|float|int|string|null>  $properties
     */
    private function caption(array $properties, string $fallback): string
    {
        foreach (['caption', 'name', 'value', 'id'] as $field) {
            $value = $properties[$field] ?? null;

            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        return $fallback;
    }
}
