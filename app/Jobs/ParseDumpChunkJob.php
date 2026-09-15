<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\FinalizeDumpIngest;
use App\Actions\ParseDumpChunk;
use App\Enums\DumpChunkStatus;
use App\Models\DumpChunk;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\UniqueFor;
use Throwable;

#[UniqueFor(300)]
final class ParseDumpChunkJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 180;

    public function __construct(public readonly string $dumpChunkId) {}

    public function uniqueId(): string
    {
        return $this->dumpChunkId;
    }

    public function handle(ParseDumpChunk $parse): void
    {
        $chunk = DumpChunk::query()->find($this->dumpChunkId);

        if ($chunk === null) {
            return;
        }

        $parse->handle($chunk);
    }

    public function failed(?Throwable $exception): void
    {
        $chunk = DumpChunk::query()->find($this->dumpChunkId);

        if ($chunk === null || $chunk->status === DumpChunkStatus::Completed) {
            return;
        }

        $chunk->update([
            'status' => DumpChunkStatus::Failed,
            'error' => 'Chunk failed after retries.',
        ]);

        resolve(FinalizeDumpIngest::class)->handle($chunk->dump);
    }
}
