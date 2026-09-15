<?php

declare(strict_types=1);

namespace App\Actions;

use App\Data\DumpChunkProgressData;
use App\Data\DumpErrorData;
use App\Data\DumpProgressData;
use App\Models\Dump;
use App\Models\DumpChunk;
use App\Models\DumpError;

final readonly class LoadDumpProgress
{
    public function __construct(private LoadDumps $dumps) {}

    public function handle(Dump $dump): DumpProgressData
    {
        $summary = $this->dumps->map($dump->load('chunks'));

        $chunks = $dump->chunks
            ->sortBy([
                ['pass', 'asc'],
                ['chunk_index', 'asc'],
            ])
            ->values()
            ->map(fn (DumpChunk $chunk): DumpChunkProgressData => new DumpChunkProgressData(
                id: $chunk->id,
                chunk_index: $chunk->chunk_index,
                pass: $chunk->pass,
                status: $chunk->status,
                line_start: $chunk->line_start,
                line_end: $chunk->line_end,
                entities_read: $chunk->entities_read,
                nodes_upserted: $chunk->nodes_upserted,
                edges_upserted: $chunk->edges_upserted,
                error: $chunk->error,
            ))
            ->all();

        /** @var list<DumpChunkProgressData> $chunks */
        $chunks = array_values($chunks);

        $errors = DumpError::query()
            ->where('dump_id', $dump->id)
            ->latest()
            ->limit(100)
            ->get()
            ->map(fn (DumpError $error): DumpErrorData => new DumpErrorData(
                id: $error->id,
                dump_chunk_id: $error->dump_chunk_id,
                line_number: $error->line_number,
                code: $error->code,
                message: $error->message,
                created_at: $error->created_at->toIso8601String(),
            ))
            ->all();

        /** @var list<DumpErrorData> $errors */
        $errors = array_values($errors);

        return new DumpProgressData(
            id: $summary->id,
            source: $summary->source,
            dataset: $summary->dataset,
            status: $summary->status,
            entities_read: $summary->entities_read,
            nodes_upserted: $summary->nodes_upserted,
            edges_upserted: $summary->edges_upserted,
            chunks_total: $summary->chunks_total,
            chunks_completed: $summary->chunks_completed,
            chunks_failed: $summary->chunks_failed,
            error_count: $summary->error_count,
            error: $summary->error,
            created_at: $summary->created_at,
            updated_at: $summary->updated_at,
            chunks: $chunks,
            errors: $errors,
        );
    }
}
