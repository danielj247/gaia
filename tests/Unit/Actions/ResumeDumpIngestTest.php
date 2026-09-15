<?php

declare(strict_types=1);

use App\Actions\ParseFollowTheMoneyDump;
use App\Actions\ResumeDumpIngest;
use App\Enums\DumpChunkPass;
use App\Enums\DumpChunkStatus;
use App\Enums\DumpStatus;
use App\Graph\GraphClient;
use App\Models\Dump;
use App\Models\DumpChunk;

it('resets failed chunks and finishes the dump without duplicating nodes', function (): void {
    $dump = Dump::factory()->create([
        'path' => base_path('tests/Fixtures/opensanctions/us_ofac_sdn.sample.jsonl'),
        'record_limit' => 10,
        'status' => DumpStatus::Pending,
    ]);

    resolve(ParseFollowTheMoneyDump::class)->handle($dump);

    $nodes = app(GraphClient::class)->stats()['nodes'];
    $chunk = DumpChunk::query()
        ->where('dump_id', $dump->id)
        ->where('pass', DumpChunkPass::Intervals)
        ->firstOrFail();

    $chunk->update([
        'status' => DumpChunkStatus::Failed,
        'error' => 'worker killed',
    ]);
    $dump->update([
        'status' => DumpStatus::Failed,
        'error' => 'One or more chunks failed.',
    ]);

    $resumed = resolve(ResumeDumpIngest::class)->handle($dump->id);

    expect($resumed->status)->toBe(DumpStatus::Completed)
        ->and(app(GraphClient::class)->stats()['nodes'])->toBe($nodes)
        ->and($chunk->fresh()?->status)->toBe(DumpChunkStatus::Completed);
});

it('returns a completed dump without dispatching work', function (): void {
    $dump = Dump::factory()->create([
        'status' => DumpStatus::Completed,
        'entities_read' => 3,
    ]);

    $resumed = resolve(ResumeDumpIngest::class)->handle($dump->id);

    expect($resumed->status)->toBe(DumpStatus::Completed)
        ->and($resumed->entities_read)->toBe(3);
});

it('fails when the dump does not exist', function (): void {
    expect(fn () => resolve(ResumeDumpIngest::class)->handle('missing-dump'))
        ->toThrow(RuntimeException::class);
});

it('slices a pending dump that has no chunks yet', function (): void {
    $dump = Dump::factory()->create([
        'path' => base_path('tests/Fixtures/opensanctions/us_ofac_sdn.sample.jsonl'),
        'record_limit' => 10,
        'status' => DumpStatus::Pending,
    ]);

    $resumed = resolve(ResumeDumpIngest::class)->handle($dump->id);

    expect($resumed->status)->toBe(DumpStatus::Completed)
        ->and($resumed->chunks)->not->toBeEmpty();
});
