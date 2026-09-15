<?php

declare(strict_types=1);

use App\Models\Dump;
use App\Models\DumpChunk;
use App\Models\DumpError;

it('exposes dump ledger relations', function (): void {
    $dump = Dump::factory()->create();
    $chunk = DumpChunk::factory()->create([
        'dump_id' => $dump->id,
    ]);
    $error = DumpError::factory()->create([
        'dump_id' => $dump->id,
        'dump_chunk_id' => $chunk->id,
    ]);

    expect($chunk->dump->id)->toBe($dump->id)
        ->and($chunk->errors)->toHaveCount(1)
        ->and($error->dump->id)->toBe($dump->id)
        ->and($error->chunk?->id)->toBe($chunk->id)
        ->and($dump->errors)->toHaveCount(1);
});
