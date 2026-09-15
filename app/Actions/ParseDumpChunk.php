<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\DumpChunkPass;
use App\Enums\DumpChunkStatus;
use App\Graph\FollowTheMoneyMapper;
use App\Models\Dump;
use App\Models\DumpChunk;
use App\Models\DumpError;
use Illuminate\Support\Facades\DB;
use Throwable;

final readonly class ParseDumpChunk
{
    public function __construct(
        private FollowTheMoneyMapper $mapper,
        private MapOfficialRecord $mapOfficial,
        private AssignPersonIds $assignPersonIds,
        private UpsertMappedGraph $upsert,
        private FinalizeDumpIngest $finalize,
    ) {}

    public function handle(DumpChunk $chunk): DumpChunk
    {
        $chunk->refresh();

        if ($chunk->status === DumpChunkStatus::Completed) {
            return $chunk;
        }

        $dump = Dump::query()->findOrFail($chunk->dump_id);
        $path = $dump->path;

        $chunk->update([
            'status' => DumpChunkStatus::Processing,
            'error' => null,
        ]);

        if (! is_string($path) || $path === '' || ! is_file($path) || ! is_readable($path)) {
            return $this->failChunk($chunk, $dump, 'Dump file is missing.');
        }

        $handle = fopen($path, 'r');

        if ($handle === false || fseek($handle, $chunk->byte_offset) !== 0) {
            if (is_resource($handle)) {
                fclose($handle);
            }

            return $this->failChunk($chunk, $dump, 'Unable to read dump file.');
        }

        $entitiesRead = 0;
        $nodes = 0;
        $edges = 0;
        $intervals = $chunk->pass === DumpChunkPass::Intervals;

        try {
            for ($lineNumber = $chunk->line_start; $lineNumber <= $chunk->line_end; $lineNumber++) {
                $line = fgets($handle);

                if ($line === false) {
                    break;
                }

                $trimmed = mb_trim($line);

                if ($trimmed === '') {
                    continue;
                }

                $decoded = json_decode($trimmed, true);

                if (! is_array($decoded)) {
                    if (! $intervals) {
                        $this->recordError($dump, $chunk, $lineNumber, 'invalid_json', 'Invalid JSON.');
                    }

                    continue;
                }

                $entity = $this->stringKeyed($decoded);

                if ($entity === []) {
                    if (! $intervals) {
                        $this->recordError($dump, $chunk, $lineNumber, 'empty_entity', 'Empty or non-object entity.');
                    }

                    continue;
                }

                $entitiesRead++;

                $official = $dump->source === 'official';

                if ($official ? $intervals : $this->mapper->isInterval($entity) !== $intervals) {
                    continue;
                }

                $id = $entity['id'] ?? null;

                if (! is_string($id) || mb_trim($id) === '') {
                    if (! $intervals) {
                        $this->recordError($dump, $chunk, $lineNumber, 'missing_id', 'Missing entity id.');
                    }

                    continue;
                }

                try {
                    $mapped = $official
                        ? $this->mapOfficial->handle($dump->dataset, $entity, $dump->id)
                        : $this->mapper->map($entity, $dump->id);

                    $counts = $this->upsert->handle(
                        $this->assignPersonIds->handle($mapped),
                    );
                } catch (Throwable $exception) {
                    $this->recordError($dump, $chunk, $lineNumber, 'graph_write', 'Graph write failed.');

                    throw $exception;
                }

                $nodes += $counts['nodes'];
                $edges += $counts['edges'];
            }
        } finally {
            fclose($handle);
        }

        $countedEntities = $chunk->pass === DumpChunkPass::Entities ? $entitiesRead : 0;

        DB::transaction(function () use ($chunk, $dump, $countedEntities, $nodes, $edges): void {
            $updated = DumpChunk::query()
                ->whereKey($chunk->id)
                ->where('status', '!=', DumpChunkStatus::Completed)
                ->update([
                    'status' => DumpChunkStatus::Completed,
                    'entities_read' => $countedEntities,
                    'nodes_upserted' => $nodes,
                    'edges_upserted' => $edges,
                    'error' => null,
                ]);

            if ($updated === 1) {
                Dump::query()->whereKey($dump->id)->incrementEach([
                    'entities_read' => $countedEntities,
                    'nodes_upserted' => $nodes,
                    'edges_upserted' => $edges,
                ]);
            }
        });

        $this->finalize->handle($dump);

        return $chunk->fresh() ?? $chunk;
    }

    private function failChunk(DumpChunk $chunk, Dump $dump, string $message): DumpChunk
    {
        $chunk->update([
            'status' => DumpChunkStatus::Failed,
            'error' => $message,
        ]);

        $this->finalize->handle($dump);

        return $chunk->fresh() ?? $chunk;
    }

    private function recordError(Dump $dump, DumpChunk $chunk, int $lineNumber, string $code, string $message): void
    {
        DumpError::query()->create([
            'dump_id' => $dump->id,
            'dump_chunk_id' => $chunk->id,
            'line_number' => $lineNumber,
            'code' => $code,
            'message' => $message,
        ]);
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
