<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\DumpChunkPass;
use App\Enums\DumpChunkStatus;
use App\Enums\DumpStatus;
use App\Models\Dump;
use App\Models\DumpChunk;
use Illuminate\Database\Eloquent\Collection;

final readonly class FinalizeDumpIngest
{
    public function __construct(
        private DispatchDumpChunks $dispatch,
        private ReleaseDumpFile $release,
    ) {}

    public function handle(Dump $dump): Dump
    {
        $chunks = $dump->chunks()->get();

        if ($chunks->isEmpty()) {
            return $dump->fresh() ?? $dump;
        }

        $entities = $chunks->where('pass', DumpChunkPass::Entities);
        $intervals = $chunks->where('pass', DumpChunkPass::Intervals);

        if ($this->hasOpenWork($entities)) {
            return $dump->fresh() ?? $dump;
        }

        if ($this->hasFailed($entities)) {
            return $this->fail($dump, 'One or more entity chunks failed.');
        }

        if ($this->hasPending($intervals) && ! $this->hasProcessing($intervals)) {
            $this->dispatch->handle($dump);
        }

        $chunks = $dump->chunks()->get();

        if ($chunks->every(fn (DumpChunk $chunk): bool => $chunk->status === DumpChunkStatus::Completed)) {
            $dump->update([
                'status' => DumpStatus::Completed,
                'error' => null,
            ]);

            return $this->release->handle($dump->fresh() ?? $dump);
        }

        if ($this->allTerminal($chunks) && $this->hasFailed($chunks)) {
            return $this->fail($dump, 'One or more chunks failed.');
        }

        return $dump->fresh() ?? $dump;
    }

    /**
     * @param  Collection<int, DumpChunk>  $chunks
     */
    private function hasOpenWork(Collection $chunks): bool
    {
        return $this->hasPending($chunks) || $this->hasProcessing($chunks);
    }

    /**
     * @param  Collection<int, DumpChunk>  $chunks
     */
    private function hasPending(Collection $chunks): bool
    {
        return $chunks->contains(
            fn (DumpChunk $chunk): bool => $chunk->status === DumpChunkStatus::Pending,
        );
    }

    /**
     * @param  Collection<int, DumpChunk>  $chunks
     */
    private function hasProcessing(Collection $chunks): bool
    {
        return $chunks->contains(
            fn (DumpChunk $chunk): bool => $chunk->status === DumpChunkStatus::Processing,
        );
    }

    /**
     * @param  Collection<int, DumpChunk>  $chunks
     */
    private function hasFailed(Collection $chunks): bool
    {
        return $chunks->contains(
            fn (DumpChunk $chunk): bool => $chunk->status === DumpChunkStatus::Failed,
        );
    }

    /**
     * @param  Collection<int, DumpChunk>  $chunks
     */
    private function allTerminal(Collection $chunks): bool
    {
        return $chunks->every(
            fn (DumpChunk $chunk): bool => $chunk->status === DumpChunkStatus::Completed
                || $chunk->status === DumpChunkStatus::Failed,
        );
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
