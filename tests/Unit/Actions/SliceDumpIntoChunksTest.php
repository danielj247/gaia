<?php

declare(strict_types=1);

use App\Actions\SliceDumpIntoChunks;
use App\Enums\DumpChunkPass;
use App\Enums\DumpStatus;
use App\Models\Dump;
use App\Models\DumpChunk;

it('slices a follow-the-money file into entity and interval chunks', function (): void {
    config(['graph.ingest.chunk_lines' => 1]);

    $dump = Dump::factory()->create([
        'path' => base_path('tests/Fixtures/opensanctions/us_ofac_sdn.sample.jsonl'),
        'record_limit' => 10,
        'status' => DumpStatus::Pending,
    ]);

    $sliced = resolve(SliceDumpIntoChunks::class)->handle($dump);

    expect($sliced->status)->toBe(DumpStatus::Ingesting)
        ->and($sliced->chunks)->toHaveCount(6)
        ->and($sliced->chunks->where('pass', DumpChunkPass::Entities))->toHaveCount(3)
        ->and($sliced->chunks->where('pass', DumpChunkPass::Intervals))->toHaveCount(3);
});

it('does not reslice an existing ledger', function (): void {
    $dump = Dump::factory()->create([
        'path' => base_path('tests/Fixtures/opensanctions/us_ofac_sdn.sample.jsonl'),
        'status' => DumpStatus::Pending,
    ]);

    resolve(SliceDumpIntoChunks::class)->handle($dump);
    resolve(SliceDumpIntoChunks::class)->handle($dump);

    expect(DumpChunk::query()->where('dump_id', $dump->id)->count())->toBe(2);
});

it('completes an empty dump without chunks', function (): void {
    $directory = storage_path('app/dumps');

    if (! is_dir($directory)) {
        mkdir($directory, 0755, true);
    }

    $path = $directory.'/empty.jsonl';
    file_put_contents($path, '');

    $dump = Dump::factory()->create([
        'path' => $path,
        'status' => DumpStatus::Pending,
    ]);

    $sliced = resolve(SliceDumpIntoChunks::class)->handle($dump);

    expect($sliced->status)->toBe(DumpStatus::Completed)
        ->and($sliced->chunks)->toHaveCount(0);
});

it('fails when the dump file is not readable', function (): void {
    $directory = storage_path('app/dumps');

    if (! is_dir($directory)) {
        mkdir($directory, 0755, true);
    }

    $path = $directory.'/unreadable.jsonl';
    file_put_contents($path, "{\"id\":\"ofac-9\"}\n");
    chmod($path, 0000);

    $dump = Dump::factory()->create([
        'path' => $path,
        'status' => DumpStatus::Pending,
    ]);

    try {
        expect(fn () => resolve(SliceDumpIntoChunks::class)->handle($dump))
            ->toThrow(RuntimeException::class);
        expect($dump->fresh()?->status)->toBe(DumpStatus::Failed);
    } finally {
        chmod($path, 0644);
        unlink($path);
    }
});

it('fails when the dump file is missing', function (): void {
    $dump = Dump::factory()->create([
        'path' => storage_path('app/dumps/missing.jsonl'),
    ]);

    expect(fn () => resolve(SliceDumpIntoChunks::class)->handle($dump))
        ->toThrow(RuntimeException::class);

    expect($dump->fresh()?->status)->toBe(DumpStatus::Failed);
});
