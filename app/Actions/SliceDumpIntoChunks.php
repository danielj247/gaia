<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\DumpChunkPass;
use App\Enums\DumpChunkStatus;
use App\Enums\DumpStatus;
use App\Enums\GraphNodeLabel;
use App\Graph\GraphClient;
use App\Models\Dump;
use App\Models\DumpChunk;
use RuntimeException;

final readonly class SliceDumpIntoChunks
{
    public function __construct(
        private GraphClient $graph,
        private ReleaseDumpFile $release,
    ) {}

    public function handle(Dump $dump): Dump
    {
        $path = $dump->path;

        if (! is_string($path) || $path === '' || ! is_file($path) || ! is_readable($path)) {
            $this->fail($dump, 'Dump file is missing.');
        }

        $this->graph->mergeNode(GraphNodeLabel::Dump, $dump->id, [
            'id' => $dump->id,
            'caption' => $dump->dataset,
            'source' => $dump->source,
            'dataset' => $dump->dataset,
            'attribution' => $dump->attribution,
        ]);

        if ($dump->chunks()->exists()) {
            $dump->update([
                'status' => DumpStatus::Ingesting,
                'error' => null,
            ]);

            return $dump->fresh() ?? $dump;
        }

        $handle = fopen($path, 'r');

        if ($handle === false) {
            $this->fail($dump, 'Unable to read dump file.');
        }

        $chunkLines = max(1, config()->integer('graph.ingest.chunk_lines'));
        $limit = $dump->record_limit;
        $lineNumber = 0;
        $linesInChunk = 0;
        $entitiesSeen = 0;
        $chunkIndex = 0;
        $startLine = 1;
        $startByte = 0;

        try {
            while (($line = fgets($handle)) !== false) {
                $lineNumber++;
                $linesInChunk++;

                $trimmed = mb_trim($line);

                if ($trimmed !== '' && $this->countableEntity($trimmed)) {
                    $entitiesSeen++;
                }

                $hitLimit = $limit !== null && $entitiesSeen >= $limit;
                $hitSize = $linesInChunk >= $chunkLines;

                if ($hitSize || $hitLimit) {
                    $this->createChunkPair($dump, $chunkIndex, $startLine, $lineNumber, $startByte);
                    $chunkIndex++;
                    $startLine = $lineNumber + 1;
                    $tell = ftell($handle);
                    $startByte = $tell === false ? 0 : $tell;
                    $linesInChunk = 0;
                }

                if ($hitLimit) {
                    break;
                }
            }

            if ($linesInChunk > 0) {
                $this->createChunkPair($dump, $chunkIndex, $startLine, $lineNumber, $startByte);
            }
        } finally {
            fclose($handle);
        }

        if ($dump->chunks()->doesntExist()) {
            $dump->update([
                'status' => DumpStatus::Completed,
                'entities_read' => 0,
                'nodes_upserted' => 0,
                'edges_upserted' => 0,
                'error' => null,
            ]);

            return $this->release->handle($dump->fresh() ?? $dump);
        }

        $dump->update([
            'status' => DumpStatus::Ingesting,
            'error' => null,
        ]);

        return $dump->fresh() ?? $dump;
    }

    private function createChunkPair(Dump $dump, int $index, int $startLine, int $endLine, int $byteOffset): void
    {
        foreach (DumpChunkPass::cases() as $pass) {
            DumpChunk::query()->create([
                'dump_id' => $dump->id,
                'chunk_index' => $index,
                'pass' => $pass,
                'line_start' => $startLine,
                'line_end' => $endLine,
                'byte_offset' => $byteOffset,
                'status' => DumpChunkStatus::Pending,
            ]);
        }
    }

    private function countableEntity(string $line): bool
    {
        $decoded = json_decode($line, true);

        if (! is_array($decoded)) {
            return false;
        }

        return $this->stringKeyed($decoded) !== [];
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

    private function fail(Dump $dump, string $message): never
    {
        $dump->update([
            'status' => DumpStatus::Failed,
            'error' => $message,
        ]);

        throw new RuntimeException($message);
    }
}
