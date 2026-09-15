<?php

declare(strict_types=1);

use App\Enums\DumpStatus;
use App\Models\Dump;
use Illuminate\Support\Facades\Http;

it('ingests a public dump from the artisan command', function (): void {
    Http::fake([
        'https://data.opensanctions.org/*' => Http::response(
            file_get_contents(base_path('tests/Fixtures/opensanctions/us_ofac_sdn.sample.jsonl')),
            200,
        ),
    ]);

    $this->artisan('graph:ingest-opensanctions', ['dataset' => 'us_ofac_sdn', '--limit' => 10])
        ->assertSuccessful();

    expect(Dump::query()->first()?->status)->toBe(DumpStatus::Completed);
});

it('ingests the sanctions dataset id through the same command', function (): void {
    Http::fake([
        'https://data.opensanctions.org/*' => Http::response(
            file_get_contents(base_path('tests/Fixtures/opensanctions/us_ofac_sdn.sample.jsonl')),
            200,
        ),
    ]);

    $this->artisan('graph:ingest-opensanctions', ['dataset' => 'sanctions', '--limit' => 10])
        ->assertSuccessful();

    expect(Dump::query()->first()?->dataset)->toBe('sanctions')
        ->and(Dump::query()->first()?->status)->toBe(DumpStatus::Completed);
});

it('fails the command when the dataset is invalid', function (): void {
    $this->artisan('graph:ingest-opensanctions', ['dataset' => 'NOPE'])
        ->assertFailed();
});

it('fails the command when the dataset is blank', function (): void {
    $this->artisan('graph:ingest-opensanctions', ['dataset' => ''])
        ->assertFailed();
});

it('queues chunk jobs instead of parsing inline', function (): void {
    Illuminate\Support\Facades\Queue::fake();

    Http::fake([
        'https://data.opensanctions.org/*' => Http::response(
            file_get_contents(base_path('tests/Fixtures/opensanctions/us_ofac_sdn.sample.jsonl')),
            200,
        ),
    ]);

    $this->artisan('graph:ingest-opensanctions', ['dataset' => 'us_ofac_sdn', '--limit' => 10])
        ->assertSuccessful();

    expect(Dump::query()->first()?->status)->toBe(DumpStatus::Ingesting)
        ->and(Dump::query()->first()?->chunks)->not->toBeEmpty();

    Illuminate\Support\Facades\Queue::assertPushed(App\Jobs\ParseDumpChunkJob::class);
});

it('resumes an existing dump', function (): void {
    Http::fake([
        'https://data.opensanctions.org/*' => Http::response(
            file_get_contents(base_path('tests/Fixtures/opensanctions/us_ofac_sdn.sample.jsonl')),
            200,
        ),
    ]);

    $this->artisan('graph:ingest-opensanctions', ['dataset' => 'us_ofac_sdn', '--limit' => 10])
        ->assertSuccessful();

    $dump = Dump::query()->firstOrFail();

    $this->artisan('graph:ingest-opensanctions', ['--resume' => $dump->id])
        ->assertSuccessful();

    expect($dump->fresh()?->status)->toBe(DumpStatus::Completed);
});

it('fails resume when the dump does not exist', function (): void {
    $this->artisan('graph:ingest-opensanctions', ['--resume' => 'missing-dump'])
        ->assertFailed();
});

it('treats a zero limit as unlimited', function (): void {
    Http::fake([
        'https://data.opensanctions.org/*' => Http::response(
            file_get_contents(base_path('tests/Fixtures/opensanctions/us_ofac_sdn.sample.jsonl')),
            200,
        ),
    ]);

    $this->artisan('graph:ingest-opensanctions', ['dataset' => 'us_ofac_sdn', '--limit' => 0])
        ->assertSuccessful();

    expect(Dump::query()->first()?->record_limit)->toBeNull();
});
