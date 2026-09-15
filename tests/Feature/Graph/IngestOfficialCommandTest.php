<?php

declare(strict_types=1);

use App\Enums\DumpStatus;
use App\Models\Dump;
use Illuminate\Support\Facades\Http;

it('ingests the uk sanctions list from the artisan command', function (): void {
    Http::fake([
        'https://sanctionslist.fcdo.gov.uk/*' => Http::response(
            file_get_contents(base_path('tests/Fixtures/official/uksl.sample.csv')),
            200,
        ),
    ]);

    $this->artisan('graph:ingest-official', ['list' => 'uksl', '--limit' => 2])
        ->assertSuccessful();

    $dump = Dump::query()->first();

    expect($dump?->source)->toBe('official')
        ->and($dump?->dataset)->toBe('uksl')
        ->and($dump?->status)->toBe(DumpStatus::Completed)
        ->and($dump?->entities_read)->toBe(2)
        ->and($dump?->path)->toBeNull();
});

it('fails the command when the list is blank', function (): void {
    $this->artisan('graph:ingest-official', ['list' => ''])
        ->assertFailed();
});

it('resumes an official dump', function (): void {
    Http::fake([
        'https://sanctionslist.fcdo.gov.uk/*' => Http::response(
            file_get_contents(base_path('tests/Fixtures/official/uksl.sample.csv')),
            200,
        ),
    ]);

    $this->artisan('graph:ingest-official', ['list' => 'uksl', '--limit' => 2])
        ->assertSuccessful();

    $dump = Dump::query()->firstOrFail();

    $this->artisan('graph:ingest-official', ['--resume' => $dump->id])
        ->assertSuccessful();

    expect($dump->fresh()?->status)->toBe(DumpStatus::Completed);
});

it('fails official resume when the dump does not exist', function (): void {
    $this->artisan('graph:ingest-official', ['--resume' => 'missing-dump'])
        ->assertFailed();
});

it('ingests ofac sdn through the official command', function (): void {
    Http::fake([
        'https://sanctionslistservice.ofac.treas.gov/*' => Http::response(
            file_get_contents(base_path('tests/Fixtures/official/ofac_sdn.sample.xml')),
            200,
        ),
    ]);

    $this->artisan('graph:ingest-official', ['list' => 'ofac_sdn', '--limit' => 2])
        ->assertSuccessful();

    expect(Dump::query()->first()?->dataset)->toBe('ofac_sdn')
        ->and(Dump::query()->first()?->status)->toBe(DumpStatus::Completed);
});

it('fails the command when the list is not allow-listed', function (): void {
    $this->artisan('graph:ingest-official', ['list' => 'default'])
        ->assertFailed();
});

it('defaults a non-numeric official limit to 400', function (): void {
    Http::fake([
        'https://sanctionslist.fcdo.gov.uk/*' => Http::response(
            file_get_contents(base_path('tests/Fixtures/official/uksl.sample.csv')),
            200,
        ),
    ]);

    $this->artisan('graph:ingest-official', ['list' => 'uksl', '--limit' => 'nope'])
        ->assertSuccessful();

    expect(Dump::query()->first()?->record_limit)->toBe(400);
});

it('treats a zero limit as the full official file', function (): void {
    Http::fake([
        'https://sanctionslist.fcdo.gov.uk/*' => Http::response(
            file_get_contents(base_path('tests/Fixtures/official/uksl.sample.csv')),
            200,
        ),
    ]);

    $this->artisan('graph:ingest-official', ['list' => 'uksl', '--limit' => 0])
        ->assertSuccessful();

    expect(Dump::query()->first()?->record_limit)->toBeNull();
});

it('queues official chunk jobs instead of parsing inline', function (): void {
    Illuminate\Support\Facades\Queue::fake();

    Http::fake([
        'https://sanctionslist.fcdo.gov.uk/*' => Http::response(
            file_get_contents(base_path('tests/Fixtures/official/uksl.sample.csv')),
            200,
        ),
    ]);

    $this->artisan('graph:ingest-official', ['list' => 'uksl', '--limit' => 2])
        ->assertSuccessful();

    expect(Dump::query()->first()?->status)->toBe(DumpStatus::Ingesting)
        ->and(Dump::query()->first()?->path)->not->toBeNull();

    Illuminate\Support\Facades\Queue::assertPushed(App\Jobs\ParseDumpChunkJob::class);
});
