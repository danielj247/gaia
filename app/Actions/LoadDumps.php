<?php

declare(strict_types=1);

namespace App\Actions;

use App\Data\DumpSummaryData;
use App\Enums\DumpChunkStatus;
use App\Models\Dump;
use App\Models\DumpError;

final readonly class LoadDumps
{
    /**
     * @return list<DumpSummaryData>
     */
    public function handle(): array
    {
        $summaries = [];

        $dumps = Dump::query()
            ->with('chunks')
            ->latest()
            ->get();

        foreach ($dumps as $dump) {
            $summaries[] = $this->map($dump);
        }

        return $summaries;
    }

    public function map(Dump $dump): DumpSummaryData
    {
        $chunks = $dump->relationLoaded('chunks')
            ? $dump->chunks
            : $dump->chunks()->get();

        $createdAt = $dump->created_at;
        $updatedAt = $dump->updated_at;

        return new DumpSummaryData(
            id: $dump->id,
            source: $dump->source,
            dataset: $dump->dataset,
            status: $dump->status,
            entities_read: $dump->entities_read,
            nodes_upserted: $dump->nodes_upserted,
            edges_upserted: $dump->edges_upserted,
            chunks_total: $chunks->count(),
            chunks_completed: $chunks->where('status', DumpChunkStatus::Completed)->count(),
            chunks_failed: $chunks->where('status', DumpChunkStatus::Failed)->count(),
            error_count: DumpError::query()->where('dump_id', $dump->id)->count(),
            error: $dump->error,
            created_at: $createdAt->toIso8601String(),
            updated_at: $updatedAt->toIso8601String(),
        );
    }
}
