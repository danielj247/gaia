<?php

declare(strict_types=1);

use App\Actions\LoadDumpProgress;
use App\Actions\LoadDumps;
use App\Enums\DumpChunkStatus;
use App\Enums\DumpStatus;
use App\Models\Dump;
use App\Models\DumpChunk;
use App\Models\DumpError;

it('returns an empty dump list', function (): void {
    expect(resolve(LoadDumps::class)->handle())->toBe([]);
});

it('summarizes chunk progress and errors', function (): void {
    $dump = Dump::factory()->create([
        'status' => DumpStatus::Ingesting,
        'entities_read' => 2,
        'error' => null,
    ]);

    DumpChunk::factory()->create([
        'dump_id' => $dump->id,
        'chunk_index' => 0,
        'status' => DumpChunkStatus::Completed,
    ]);
    DumpChunk::factory()->create([
        'dump_id' => $dump->id,
        'chunk_index' => 1,
        'status' => DumpChunkStatus::Failed,
        'error' => 'Dump file is missing.',
    ]);
    DumpError::factory()->create([
        'dump_id' => $dump->id,
        'code' => 'invalid_json',
        'message' => 'Invalid JSON.',
        'line_number' => 4,
    ]);

    $summaries = resolve(LoadDumps::class)->handle();

    expect($summaries)->toHaveCount(1)
        ->and($summaries[0]->id)->toBe($dump->id)
        ->and($summaries[0]->chunks_total)->toBe(2)
        ->and($summaries[0]->chunks_completed)->toBe(1)
        ->and($summaries[0]->chunks_failed)->toBe(1)
        ->and($summaries[0]->error_count)->toBe(1);

    $progress = resolve(LoadDumpProgress::class)->handle($dump);

    expect($progress->chunks)->toHaveCount(2)
        ->and($progress->errors)->toHaveCount(1)
        ->and($progress->errors[0]->code)->toBe('invalid_json');

    $fresh = $dump->fresh();

    expect($fresh)->not->toBeNull()
        ->and(resolve(LoadDumps::class)->map($fresh)->chunks_total)->toBe(2);
});
