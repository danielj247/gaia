<?php

declare(strict_types=1);

use App\Actions\ParseFollowTheMoneyDump;
use App\Enums\DumpStatus;
use App\Enums\GraphEdgeType;
use App\Graph\GraphClient;
use App\Models\Dump;

it('parses a follow-the-money file into the graph', function (): void {
    $dump = Dump::factory()->create([
        'path' => base_path('tests/Fixtures/opensanctions/us_ofac_sdn.sample.jsonl'),
        'record_limit' => 10,
        'status' => DumpStatus::Pending,
    ]);

    $parsed = resolve(ParseFollowTheMoneyDump::class)->handle($dump);

    expect($parsed->status)->toBe(DumpStatus::Completed)
        ->and($parsed->entities_read)->toBe(3)
        ->and($parsed->nodes_upserted)->toBeGreaterThan(0)
        ->and(app(GraphClient::class)->stats()['nodes'])->toBeGreaterThan(0);
});

it('skips blank invalid and list-only lines', function (): void {
    $directory = storage_path('app/dumps');

    if (! is_dir($directory)) {
        mkdir($directory, 0755, true);
    }

    $path = $directory.'/skip-lines.jsonl';
    file_put_contents($path, "\nnot-json\n[]\n{\"schema\":\"Person\"}\n{\"id\":\"ofac-9\",\"schema\":\"Person\",\"caption\":\"Ada\"}\n");

    $dump = Dump::factory()->create([
        'path' => $path,
        'status' => DumpStatus::Pending,
    ]);

    $parsed = resolve(ParseFollowTheMoneyDump::class)->handle($dump);

    expect($parsed->status)->toBe(DumpStatus::Completed)
        ->and($parsed->entities_read)->toBe(2)
        ->and($parsed->errors()->count())->toBe(3)
        ->and($parsed->chunks)->not->toBeEmpty();
});

it('attaches interval edges when they appear before their endpoints', function (): void {
    $directory = storage_path('app/dumps');

    if (! is_dir($directory)) {
        mkdir($directory, 0755, true);
    }

    $path = $directory.'/interval-first.jsonl';
    file_put_contents($path, implode("\n", [
        '{"id":"ofac-rel-1","schema":"Ownership","properties":{"owner":["ofac-100"],"asset":["ofac-200"]}}',
        '{"id":"ofac-100","caption":"Ada Example","schema":"Person","properties":{"name":["Ada Example"]}}',
        '{"id":"ofac-200","caption":"Example Holdings","schema":"Organization","properties":{"name":["Example Holdings"]}}',
        '',
    ]));

    $dump = Dump::factory()->create([
        'path' => $path,
        'status' => DumpStatus::Pending,
    ]);

    resolve(ParseFollowTheMoneyDump::class)->handle($dump);

    $graph = app(GraphClient::class);
    $personId = collect($graph->search('ofac-100', 5))->firstWhere('label', 'Person')['id'] ?? null;
    $types = collect($graph->neighborhood((string) $personId, 1, 50)->edges)->pluck('type');

    expect($personId)->not->toBeNull()
        ->and($personId)->not->toBe('ofac-100')
        ->and($types)->toContain(GraphEdgeType::Owns->value);
});

it('fails when the dump file is missing', function (): void {
    $dump = Dump::factory()->create([
        'path' => storage_path('app/dumps/missing.jsonl'),
    ]);

    expect(fn () => resolve(ParseFollowTheMoneyDump::class)->handle($dump))
        ->toThrow(RuntimeException::class);

    expect($dump->fresh()?->status)->toBe(DumpStatus::Failed);
});
