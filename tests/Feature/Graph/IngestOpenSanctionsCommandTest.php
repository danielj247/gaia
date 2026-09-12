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

it('fails the command when the dataset is invalid', function (): void {
    $this->artisan('graph:ingest-opensanctions', ['dataset' => 'NOPE'])
        ->assertFailed();
});

it('fails the command when the dataset is blank', function (): void {
    $this->artisan('graph:ingest-opensanctions', ['dataset' => ''])
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
