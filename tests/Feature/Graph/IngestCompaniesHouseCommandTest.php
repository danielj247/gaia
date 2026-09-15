<?php

declare(strict_types=1);

use App\Enums\DumpStatus;
use App\Models\Dump;
use Illuminate\Support\Facades\Queue;

it('ingests the companies house company product from the artisan command', function (): void {
    fakeCompaniesHouseCompanies();

    $this->artisan('graph:ingest-companies-house', ['product' => 'ch_companies', '--limit' => 2])
        ->assertSuccessful();

    $dump = Dump::query()->first();

    expect($dump?->source)->toBe('companies_house')
        ->and($dump?->dataset)->toBe('ch_companies')
        ->and($dump?->status)->toBe(DumpStatus::Completed)
        ->and($dump?->entities_read)->toBe(2)
        ->and($dump?->path)->toBeNull();
});

it('treats a zero psc part as a sequential walk', function (): void {
    fakeCompaniesHousePscParts();

    $this->artisan('graph:ingest-companies-house', ['product' => 'ch_psc', '--part' => 0, '--limit' => 1])
        ->assertSuccessful();

    expect(Dump::query()->count())->toBe(2)
        ->and(Dump::query()->pluck('dataset')->unique()->all())->toBe(['ch_psc']);
});

it('fails the command when the product is blank', function (): void {
    $this->artisan('graph:ingest-companies-house', ['product' => ''])
        ->assertFailed();
});

it('resumes a companies house dump', function (): void {
    fakeCompaniesHouseCompanies();

    $this->artisan('graph:ingest-companies-house', ['product' => 'ch_companies', '--limit' => 2])
        ->assertSuccessful();

    $dump = Dump::query()->firstOrFail();

    $this->artisan('graph:ingest-companies-house', ['--resume' => $dump->id])
        ->assertSuccessful();

    expect($dump->fresh()?->status)->toBe(DumpStatus::Completed);
});

it('fails companies house resume when the dump does not exist', function (): void {
    $this->artisan('graph:ingest-companies-house', ['--resume' => 'missing-dump'])
        ->assertFailed();
});

it('fails the command when the product is not allow-listed', function (): void {
    $this->artisan('graph:ingest-companies-house', ['product' => 'gb_coh_psc'])
        ->assertFailed();
});

it('defaults a non-numeric companies house limit to 400', function (): void {
    fakeCompaniesHouseCompanies();

    $this->artisan('graph:ingest-companies-house', ['product' => 'ch_companies', '--limit' => 'nope'])
        ->assertSuccessful();

    expect(Dump::query()->first()?->record_limit)->toBe(400);
});

it('treats a zero companies house limit as the full file', function (): void {
    fakeCompaniesHouseCompanies();

    $this->artisan('graph:ingest-companies-house', ['product' => 'ch_companies', '--limit' => 0])
        ->assertSuccessful();

    expect(Dump::query()->first()?->record_limit)->toBeNull()
        ->and(Dump::query()->first()?->entities_read)->toBe(3);
});

it('ingests one companies house psc part from the artisan command', function (): void {
    fakeCompaniesHousePscParts();

    $this->artisan('graph:ingest-companies-house', ['product' => 'ch_psc', '--part' => 1, '--limit' => 2])
        ->assertSuccessful();

    $dump = Dump::query()->first();

    expect($dump?->dataset)->toBe('ch_psc')
        ->and($dump?->status)->toBe(DumpStatus::Completed)
        ->and($dump?->entities_read)->toBe(2)
        ->and($dump?->path)->toBeNull();
});

it('queues companies house chunk jobs instead of parsing inline', function (): void {
    Queue::fake();

    fakeCompaniesHouseCompanies();

    $this->artisan('graph:ingest-companies-house', ['product' => 'ch_companies', '--limit' => 2])
        ->assertSuccessful();

    expect(Dump::query()->first()?->status)->toBe(DumpStatus::Ingesting)
        ->and(Dump::query()->first()?->path)->not->toBeNull();

    Queue::assertPushed(App\Jobs\ParseDumpChunkJob::class);
});
