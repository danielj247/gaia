<?php

declare(strict_types=1);

namespace App\Graph;

use Laudis\Neo4j\Contracts\ClientInterface;
use Laudis\Neo4j\Types\CypherList;
use Laudis\Neo4j\Types\CypherMap;
use Laudis\Neo4j\Types\Node;
use Laudis\Neo4j\Types\Relationship;

final readonly class LaudisGraphSession implements GraphSession
{
    public function __construct(private ClientInterface $client) {}

    /**
     * @param  array<string, mixed>  $parameters
     * @return list<array<string, mixed>>
     */
    public function run(string $statement, array $parameters = []): array
    {
        $rows = [];

        foreach ($this->client->run($statement, $parameters) as $record) {
            $row = [];

            foreach ($record as $key => $value) {
                $row[$key] = $this->normalize($value);
            }

            $rows[] = $row;
        }

        return $rows;
    }

    private function normalize(mixed $value): mixed
    {
        if ($value instanceof Node) {
            $properties = $this->mapToArray($value->getProperties());
            $id = $properties['id'] ?? null;

            return [
                'id' => is_string($id) ? $id : null,
                'labels' => $value->getLabels()->toArray(),
                'properties' => $properties,
            ];
        }

        if ($value instanceof Relationship) {
            return [
                'type' => $value->getType(),
                'start' => (string) $value->getStartNodeId(),
                'end' => (string) $value->getEndNodeId(),
                'properties' => $this->mapToArray($value->getProperties()),
            ];
        }

        if ($value instanceof CypherMap) {
            return $this->mapToArray($value);
        }

        if ($value instanceof CypherList) {
            return array_map($this->normalize(...), $value->toArray());
        }

        return $value;
    }

    /**
     * @param  iterable<string, mixed>  $map
     * @return array<string, mixed>
     */
    private function mapToArray(iterable $map): array
    {
        $values = [];

        foreach ($map as $key => $value) {
            $values[$key] = $this->normalize($value);
        }

        return $values;
    }
}
