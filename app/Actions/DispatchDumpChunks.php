<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\DumpChunkPass;
use App\Enums\DumpChunkStatus;
use App\Jobs\ParseDumpChunkJob;
use App\Models\Dump;
use App\Models\DumpChunk;

final readonly class DispatchDumpChunks
{
    public function handle(Dump $dump): void
    {
        $ids = $this->pendingIds($dump, DumpChunkPass::Entities);

        if ($ids === []) {
            $ids = $this->pendingIds($dump, DumpChunkPass::Intervals);
        }

        foreach ($ids as $id) {
            ParseDumpChunkJob::dispatch($id);
        }
    }

    /**
     * @return list<string>
     */
    private function pendingIds(Dump $dump, DumpChunkPass $pass): array
    {
        $ids = [];

        $chunks = DumpChunk::query()
            ->where('dump_id', $dump->id)
            ->where('pass', $pass)
            ->whereIn('status', [DumpChunkStatus::Pending, DumpChunkStatus::Failed])
            ->orderBy('chunk_index')
            ->get();

        foreach ($chunks as $chunk) {
            $ids[] = $chunk->id;
        }

        return $ids;
    }
}
