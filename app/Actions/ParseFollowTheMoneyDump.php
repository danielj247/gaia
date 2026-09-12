<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\DumpStatus;
use App\Enums\GraphNodeLabel;
use App\Graph\FollowTheMoneyMapper;
use App\Graph\GraphClient;
use App\Models\Dump;
use RuntimeException;

final readonly class ParseFollowTheMoneyDump
{
    public function __construct(
        private GraphClient $graph,
        private FollowTheMoneyMapper $mapper,
        private UpsertMappedGraph $upsert,
    ) {}

    public function handle(Dump $dump): Dump
    {
        $path = $dump->path;

        if (! is_string($path) || $path === '' || ! is_file($path)) {
            $dump->update([
                'status' => DumpStatus::Failed,
                'error' => 'Dump file is missing.',
            ]);

            throw new RuntimeException('Dump file is missing.');
        }

        $dump->update(['status' => DumpStatus::Ingesting]);

        $this->graph->mergeNode(GraphNodeLabel::Dump, $dump->id, [
            'id' => $dump->id,
            'caption' => $dump->dataset,
            'source' => $dump->source,
            'dataset' => $dump->dataset,
            'attribution' => $dump->attribution,
        ]);

        $entitiesRead = 0;
        $nodes = 0;
        $edges = 0;
        $limit = $dump->record_limit;

        $handle = fopen($path, 'r');

        if ($handle === false) {
            $dump->update([
                'status' => DumpStatus::Failed,
                'error' => 'Unable to read dump file.',
            ]);

            throw new RuntimeException('Unable to read dump file.');
        }

        try {
            while (($line = fgets($handle)) !== false) {
                $line = mb_trim($line);

                if ($line === '') {
                    continue;
                }

                $decoded = json_decode($line, true);

                if (! is_array($decoded)) {
                    continue;
                }

                $entity = $this->stringKeyed($decoded);

                if ($entity === []) {
                    continue;
                }

                $mapped = $this->mapper->map($entity, $dump->id);
                $counts = $this->upsert->handle($mapped);
                $entitiesRead++;
                $nodes += $counts['nodes'];
                $edges += $counts['edges'];

                if ($limit !== null && $entitiesRead >= $limit) {
                    break;
                }
            }
        } finally {
            fclose($handle);
        }

        $dump->update([
            'status' => DumpStatus::Completed,
            'entities_read' => $entitiesRead,
            'nodes_upserted' => $nodes,
            'edges_upserted' => $edges,
            'error' => null,
        ]);

        return $dump->fresh() ?? $dump;
    }

    /**
     * @param  array<mixed, mixed>  $value
     * @return array<string, mixed>
     */
    private function stringKeyed(array $value): array
    {
        $entity = [];

        foreach ($value as $key => $item) {
            if (is_string($key)) {
                $entity[$key] = $item;
            }
        }

        return $entity;
    }
}
