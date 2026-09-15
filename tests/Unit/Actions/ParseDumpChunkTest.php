<?php

declare(strict_types=1);

use App\Actions\ParseDumpChunk;
use App\Actions\ParseFollowTheMoneyDump;
use App\Enums\DumpChunkPass;
use App\Enums\DumpChunkStatus;
use App\Enums\DumpStatus;
use App\Graph\GraphClient;
use App\Models\Dump;
use App\Models\DumpChunk;
use App\Models\DumpError;

it('merges only after a deterministic id and records parse errors', function (): void {
    $directory = storage_path('app/dumps');

    if (! is_dir($directory)) {
        mkdir($directory, 0755, true);
    }

    $path = $directory.'/parse-errors.jsonl';
    file_put_contents($path, "\nnot-json\n[]\n{\"schema\":\"Person\"}\n{\"id\":\"ofac-9\",\"schema\":\"Person\",\"caption\":\"Ada Example\"}\n");

    $dump = Dump::factory()->create([
        'path' => $path,
        'status' => DumpStatus::Pending,
    ]);

    $parsed = resolve(ParseFollowTheMoneyDump::class)->handle($dump);

    expect($parsed->status)->toBe(DumpStatus::Completed)
        ->and($parsed->entities_read)->toBe(2)
        ->and($parsed->errors()->pluck('code')->all())->toContain('invalid_json', 'empty_entity', 'missing_id')
        ->and(app(GraphClient::class)->search('ofac-9', 5))->not->toBeEmpty();
});

it('does not duplicate nodes when the same chunk is parsed twice', function (): void {
    $dump = Dump::factory()->create([
        'path' => base_path('tests/Fixtures/opensanctions/us_ofac_sdn.sample.jsonl'),
        'record_limit' => 10,
        'status' => DumpStatus::Pending,
    ]);

    resolve(ParseFollowTheMoneyDump::class)->handle($dump);

    $nodes = app(GraphClient::class)->stats()['nodes'];
    $chunk = DumpChunk::query()->where('dump_id', $dump->id)->where('pass', DumpChunkPass::Entities)->firstOrFail();

    $chunk->update(['status' => DumpChunkStatus::Pending]);

    resolve(ParseDumpChunk::class)->handle($chunk);

    expect(app(GraphClient::class)->stats()['nodes'])->toBe($nodes)
        ->and($chunk->fresh()?->status)->toBe(DumpChunkStatus::Completed);
});

it('completes a chunk when the file is truncated after slicing', function (): void {
    $directory = storage_path('app/dumps');

    if (! is_dir($directory)) {
        mkdir($directory, 0755, true);
    }

    $path = $directory.'/truncated.jsonl';
    file_put_contents($path, "{\"id\":\"ofac-9\",\"schema\":\"Person\",\"caption\":\"Ada Example\"}\n");

    $dump = Dump::factory()->create([
        'path' => $path,
        'status' => DumpStatus::Pending,
    ]);

    resolve(App\Actions\SliceDumpIntoChunks::class)->handle($dump);
    file_put_contents($path, '');

    $chunk = DumpChunk::query()->where('dump_id', $dump->id)->where('pass', DumpChunkPass::Entities)->firstOrFail();
    $parsed = resolve(ParseDumpChunk::class)->handle($chunk);

    expect($parsed->status)->toBe(DumpChunkStatus::Completed);
});

it('fails a chunk when the dump file disappears after slicing', function (): void {
    $directory = storage_path('app/dumps');

    if (! is_dir($directory)) {
        mkdir($directory, 0755, true);
    }

    $path = $directory.'/vanishes.jsonl';
    file_put_contents($path, "{\"id\":\"ofac-9\",\"schema\":\"Person\",\"caption\":\"Ada Example\"}\n");

    $dump = Dump::factory()->create([
        'path' => $path,
        'status' => DumpStatus::Pending,
    ]);

    resolve(App\Actions\SliceDumpIntoChunks::class)->handle($dump);
    unlink($path);

    $chunk = DumpChunk::query()->where('dump_id', $dump->id)->where('pass', DumpChunkPass::Entities)->firstOrFail();
    $parsed = resolve(ParseDumpChunk::class)->handle($chunk);

    expect($parsed->status)->toBe(DumpChunkStatus::Failed)
        ->and($dump->fresh()?->status)->toBe(DumpStatus::Failed);
});

it('returns an already completed chunk without incrementing counters', function (): void {
    $dump = Dump::factory()->create([
        'path' => base_path('tests/Fixtures/opensanctions/us_ofac_sdn.sample.jsonl'),
        'status' => DumpStatus::Completed,
        'entities_read' => 3,
    ]);

    $chunk = DumpChunk::factory()->create([
        'dump_id' => $dump->id,
        'status' => DumpChunkStatus::Completed,
        'entities_read' => 1,
    ]);

    $parsed = resolve(ParseDumpChunk::class)->handle($chunk);

    expect($parsed->status)->toBe(DumpChunkStatus::Completed)
        ->and($dump->fresh()?->entities_read)->toBe(3);
});

it('records a graph write error and rethrows', function (): void {
    $dump = Dump::factory()->create([
        'path' => base_path('tests/Fixtures/opensanctions/us_ofac_sdn.sample.jsonl'),
        'status' => DumpStatus::Pending,
    ]);

    resolve(App\Actions\SliceDumpIntoChunks::class)->handle($dump);

    $this->mock(GraphClient::class, function ($mock): void {
        $mock->shouldReceive('findPersonId')->andReturn(null);
        $mock->shouldReceive('mergeNode')->andThrow(new RuntimeException('bolt down'));
        $mock->shouldReceive('mergeEdge')->andThrow(new RuntimeException('bolt down'));
    });

    $chunk = DumpChunk::query()->where('dump_id', $dump->id)->where('pass', DumpChunkPass::Entities)->firstOrFail();

    expect(fn () => resolve(ParseDumpChunk::class)->handle($chunk))
        ->toThrow(RuntimeException::class);

    expect(DumpError::query()->where('dump_id', $dump->id)->where('code', 'graph_write')->exists())->toBeTrue();
});
