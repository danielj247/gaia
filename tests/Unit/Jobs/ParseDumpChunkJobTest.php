<?php

declare(strict_types=1);

use App\Actions\ParseDumpChunk;
use App\Enums\DumpChunkStatus;
use App\Enums\DumpStatus;
use App\Jobs\ParseDumpChunkJob;
use App\Models\Dump;
use App\Models\DumpChunk;

it('exposes a unique id and ignores a missing chunk', function (): void {
    $job = new ParseDumpChunkJob('missing-chunk');

    expect($job->uniqueId())->toBe('missing-chunk');

    $job->handle(resolve(ParseDumpChunk::class));
    $job->failed(null);
});

it('marks a chunk failed after retries are exhausted', function (): void {
    $dump = Dump::factory()->create([
        'path' => base_path('tests/Fixtures/opensanctions/us_ofac_sdn.sample.jsonl'),
        'status' => DumpStatus::Ingesting,
    ]);
    $chunk = DumpChunk::factory()->create([
        'dump_id' => $dump->id,
        'status' => DumpChunkStatus::Processing,
    ]);

    $job = new ParseDumpChunkJob($chunk->id);
    $job->failed(new RuntimeException('worker killed'));

    expect($chunk->fresh()?->status)->toBe(DumpChunkStatus::Failed)
        ->and($chunk->fresh()?->error)->toBe('Chunk failed after retries.')
        ->and($dump->fresh()?->status)->toBe(DumpStatus::Failed);
});

it('does not reopen a completed chunk from failed()', function (): void {
    $dump = Dump::factory()->create([
        'status' => DumpStatus::Completed,
    ]);
    $chunk = DumpChunk::factory()->create([
        'dump_id' => $dump->id,
        'status' => DumpChunkStatus::Completed,
    ]);

    $job = new ParseDumpChunkJob($chunk->id);
    $job->failed(null);

    expect($chunk->fresh()?->status)->toBe(DumpChunkStatus::Completed)
        ->and($dump->fresh()?->status)->toBe(DumpStatus::Completed);
});
