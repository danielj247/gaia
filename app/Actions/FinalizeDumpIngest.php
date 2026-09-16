<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\DumpChunkPass;
use App\Enums\DumpChunkStatus;
use App\Enums\DumpStatus;
use App\Models\Dump;
use App\Models\DumpChunk;

final readonly class FinalizeDumpIngest
{
    public function __construct(
        private DispatchDumpChunks $dispatch,
        private ReleaseDumpFile $release,
    ) {}

    /**
     * Runs after every chunk, so it must only read grouped counts: a full company
     * dump has ~45k chunk rows and loading them each time cost more than the chunk.
     */
    public function handle(Dump $dump): Dump
    {
        $counts = $this->counts($dump);

        if ($counts === []) {
            return $dump->fresh() ?? $dump;
        }

        $entities = $counts[DumpChunkPass::Entities->value] ?? [];
        $intervals = $counts[DumpChunkPass::Intervals->value] ?? [];

        if ($this->has($entities, DumpChunkStatus::Pending) || $this->has($entities, DumpChunkStatus::Processing)) {
            return $dump->fresh() ?? $dump;
        }

        if ($this->has($entities, DumpChunkStatus::Failed)) {
            return $this->fail($dump, 'One or more entity chunks failed.');
        }

        if ($this->has($intervals, DumpChunkStatus::Pending) && ! $this->has($intervals, DumpChunkStatus::Processing)) {
            $this->dispatch->handle($dump);
        }

        $total = 0;
        $completed = 0;
        $failed = 0;

        foreach ($this->counts($dump) as $byStatus) {
            foreach ($byStatus as $status => $count) {
                $total += $count;
                $completed += $status === DumpChunkStatus::Completed->value ? $count : 0;
                $failed += $status === DumpChunkStatus::Failed->value ? $count : 0;
            }
        }

        if ($completed === $total) {
            $dump->update([
                'status' => DumpStatus::Completed,
                'error' => null,
            ]);

            return $this->release->handle($dump->fresh() ?? $dump);
        }

        if ($failed > 0 && $completed + $failed === $total) {
            return $this->fail($dump, 'One or more chunks failed.');
        }

        return $dump->fresh() ?? $dump;
    }

    /**
     * @return array<string, array<string, int>> pass => status => count
     */
    private function counts(Dump $dump): array
    {
        $counts = [];

        $rows = DumpChunk::query()
            ->where('dump_id', $dump->id)
            ->selectRaw('pass, status, count(*) as total')
            ->groupBy('pass', 'status')
            ->get();

        foreach ($rows as $row) {
            $total = $row->getAttribute('total');
            $counts[$row->pass->value][$row->status->value] = is_numeric($total) ? (int) $total : 0;
        }

        return $counts;
    }

    /**
     * @param  array<string, int>  $byStatus
     */
    private function has(array $byStatus, DumpChunkStatus $status): bool
    {
        return ($byStatus[$status->value] ?? 0) > 0;
    }

    private function fail(Dump $dump, string $message): Dump
    {
        $dump->update([
            'status' => DumpStatus::Failed,
            'error' => $message,
        ]);

        return $dump->fresh() ?? $dump;
    }
}
