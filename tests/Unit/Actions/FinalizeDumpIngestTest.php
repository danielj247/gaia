<?php

declare(strict_types=1);

use App\Actions\FinalizeDumpIngest;
use App\Enums\DumpChunkPass;
use App\Enums\DumpChunkStatus;
use App\Enums\DumpStatus;
use App\Jobs\ParseDumpChunkJob;
use App\Models\Dump;
use App\Models\DumpChunk;
use Illuminate\Database\Eloquent\Factories\Sequence;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

it('leaves a dump without chunks unchanged', function (): void {
    $dump = Dump::factory()->create([
        'status' => DumpStatus::Pending,
    ]);

    $finalized = resolve(FinalizeDumpIngest::class)->handle($dump);

    expect($finalized->status)->toBe(DumpStatus::Pending);
});

it('waits while entity chunks are still processing', function (): void {
    $dump = Dump::factory()->create([
        'status' => DumpStatus::Ingesting,
    ]);
    DumpChunk::factory()->create([
        'dump_id' => $dump->id,
        'pass' => DumpChunkPass::Entities,
        'status' => DumpChunkStatus::Processing,
    ]);

    $finalized = resolve(FinalizeDumpIngest::class)->handle($dump);

    expect($finalized->status)->toBe(DumpStatus::Ingesting);
});

it('dispatches interval chunks after the entity pass completes', function (): void {
    Queue::fake();

    $dump = Dump::factory()->create([
        'status' => DumpStatus::Ingesting,
    ]);
    DumpChunk::factory()->create([
        'dump_id' => $dump->id,
        'pass' => DumpChunkPass::Entities,
        'status' => DumpChunkStatus::Completed,
    ]);
    $interval = DumpChunk::factory()->create([
        'dump_id' => $dump->id,
        'pass' => DumpChunkPass::Intervals,
        'status' => DumpChunkStatus::Pending,
    ]);

    resolve(FinalizeDumpIngest::class)->handle($dump);

    Queue::assertPushed(ParseDumpChunkJob::class, fn (ParseDumpChunkJob $job): bool => $job->dumpChunkId === $interval->id);
});

it('waits while interval chunks are processing', function (): void {
    $dump = Dump::factory()->create([
        'status' => DumpStatus::Ingesting,
    ]);
    DumpChunk::factory()->create([
        'dump_id' => $dump->id,
        'pass' => DumpChunkPass::Entities,
        'status' => DumpChunkStatus::Completed,
    ]);
    DumpChunk::factory()->create([
        'dump_id' => $dump->id,
        'pass' => DumpChunkPass::Intervals,
        'status' => DumpChunkStatus::Processing,
    ]);

    $finalized = resolve(FinalizeDumpIngest::class)->handle($dump);

    expect($finalized->status)->toBe(DumpStatus::Ingesting);
});

it('finalizes from chunk counts instead of loading every chunk row', function (): void {
    $dump = Dump::factory()->create([
        'status' => DumpStatus::Ingesting,
    ]);
    DumpChunk::factory()->count(120)->sequence(fn (Sequence $sequence): array => ['chunk_index' => $sequence->index])->create([
        'dump_id' => $dump->id,
        'pass' => DumpChunkPass::Entities,
        'status' => DumpChunkStatus::Completed,
    ]);
    DumpChunk::factory()->count(60)->sequence(fn (Sequence $sequence): array => ['chunk_index' => $sequence->index])->create([
        'dump_id' => $dump->id,
        'pass' => DumpChunkPass::Intervals,
        'status' => DumpChunkStatus::Processing,
    ]);

    $chunkQueries = [];

    DB::listen(function (QueryExecuted $query) use (&$chunkQueries): void {
        if (str_contains($query->sql, 'dump_chunks')) {
            $chunkQueries[] = $query->sql;
        }
    });

    $finalized = resolve(FinalizeDumpIngest::class)->handle($dump);

    expect($finalized->status)->toBe(DumpStatus::Ingesting)
        ->and($chunkQueries)->not->toBeEmpty()
        ->and(collect($chunkQueries)->every(fn (string $sql): bool => str_contains($sql, 'count(')))->toBeTrue();
});

it('fails the dump when interval chunks fail', function (): void {
    $dump = Dump::factory()->create([
        'status' => DumpStatus::Ingesting,
    ]);
    DumpChunk::factory()->create([
        'dump_id' => $dump->id,
        'pass' => DumpChunkPass::Entities,
        'status' => DumpChunkStatus::Completed,
    ]);
    DumpChunk::factory()->create([
        'dump_id' => $dump->id,
        'pass' => DumpChunkPass::Intervals,
        'status' => DumpChunkStatus::Failed,
    ]);

    $finalized = resolve(FinalizeDumpIngest::class)->handle($dump);

    expect($finalized->status)->toBe(DumpStatus::Failed)
        ->and($finalized->error)->toBe('One or more chunks failed.');
});
