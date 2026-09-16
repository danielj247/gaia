<?php

declare(strict_types=1);

use App\Actions\IngestCompaniesHouseDump;
use App\Actions\ParseDumpChunk;
use App\Actions\ParseFollowTheMoneyDump;
use App\Actions\SliceDumpIntoChunks;
use App\Enums\DumpChunkPass;
use App\Enums\DumpChunkStatus;
use App\Enums\DumpStatus;
use App\Graph\GraphClient;
use App\Graph\Neo4jGraphClient;
use App\Graph\RecordingGraphSession;
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
        ->and(resolve(GraphClient::class)->search('ofac-9', 5))->not->toBeEmpty();
});

it('does not duplicate nodes when the same chunk is parsed twice', function (): void {
    $dump = Dump::factory()->create([
        'path' => base_path('tests/Fixtures/opensanctions/us_ofac_sdn.sample.jsonl'),
        'record_limit' => 10,
        'status' => DumpStatus::Pending,
    ]);

    resolve(ParseFollowTheMoneyDump::class)->handle($dump);

    $nodes = resolve(GraphClient::class)->stats()['nodes'];
    $chunk = DumpChunk::query()->where('dump_id', $dump->id)->where('pass', DumpChunkPass::Entities)->firstOrFail();

    $chunk->update(['status' => DumpChunkStatus::Pending]);

    resolve(ParseDumpChunk::class)->handle($chunk);

    expect(resolve(GraphClient::class)->stats()['nodes'])->toBe($nodes)
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

    resolve(SliceDumpIntoChunks::class)->handle($dump);
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

    resolve(SliceDumpIntoChunks::class)->handle($dump);
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

    resolve(SliceDumpIntoChunks::class)->handle($dump);

    $this->mock(GraphClient::class, function ($mock): void {
        $mock->shouldReceive('findPersonId')->andReturn(null);
        $mock->shouldReceive('mergeGraph')->once()->andThrow(new RuntimeException('bolt down'));
    });

    $chunk = DumpChunk::query()->where('dump_id', $dump->id)->where('pass', DumpChunkPass::Entities)->firstOrFail();

    expect(fn () => resolve(ParseDumpChunk::class)->handle($chunk))
        ->toThrow(RuntimeException::class);

    $error = DumpError::query()->where('dump_id', $dump->id)->where('code', 'graph_write')->firstOrFail();

    expect($error->line_number)->toBe($chunk->line_start)
        ->and($error->message)->toContain((string) $chunk->line_end)
        ->and($chunk->fresh()?->status)->toBe(DumpChunkStatus::Processing);
});

it('records a mapping error against the offending line and rethrows', function (): void {
    $directory = storage_path('app/dumps');

    if (! is_dir($directory)) {
        mkdir($directory, 0755, true);
    }

    $path = $directory.'/bad-map.jsonl';
    file_put_contents($path, "{\"id\":\"ofac-1\",\"schema\":\"Person\",\"caption\":\"Ada\"}\n{\"id\":\"ofac-2\",\"schema\":\"Person\",\"caption\":\"Bea\"}\n");

    $dump = Dump::factory()->create([
        'path' => $path,
        'status' => DumpStatus::Pending,
    ]);

    resolve(SliceDumpIntoChunks::class)->handle($dump);

    $this->mock(GraphClient::class, function ($mock): void {
        $mock->shouldReceive('findPersonId')->once()->andReturn(null);
        $mock->shouldReceive('findPersonId')->once()->andThrow(new RuntimeException('bolt down'));
        $mock->shouldNotReceive('mergeGraph');
    });

    $chunk = DumpChunk::query()->where('dump_id', $dump->id)->where('pass', DumpChunkPass::Entities)->firstOrFail();

    expect(fn () => resolve(ParseDumpChunk::class)->handle($chunk))
        ->toThrow(RuntimeException::class, 'bolt down')
        ->and(DumpError::query()->where('dump_id', $dump->id)->pluck('line_number', 'code')->all())->toBe(['map' => 2]);
});

it('writes a whole companies house chunk with one statement per label and edge group', function (): void {
    $session = new RecordingGraphSession([]);
    app()->instance(GraphClient::class, new Neo4jGraphClient($session));

    fakeCompaniesHouseCompanies();

    $dump = resolve(IngestCompaniesHouseDump::class)->handle('ch_companies', null);

    $merges = collect($session->statements())->filter(
        fn (array $statement): bool => str_contains($statement['statement'], 'MERGE'),
    );
    $batches = $merges->filter(
        fn (array $statement): bool => str_starts_with($statement['statement'], 'UNWIND $rows AS row'),
    );
    $organizations = $batches->first(
        fn (array $statement): bool => str_contains($statement['statement'], 'MERGE (n:Organization {id: row.id})'),
    );

    expect($dump->status)->toBe(DumpStatus::Completed)
        ->and($dump->entities_read)->toBe(3)
        ->and($dump->nodes_upserted)->toBe(12)
        ->and($dump->edges_upserted)->toBe(12)
        ->and($merges)->toHaveCount(9)
        ->and($batches)->toHaveCount(8)
        ->and($organizations['parameters']['rows'])->toHaveCount(3);
});
