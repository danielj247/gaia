<?php

declare(strict_types=1);

use App\Actions\FetchCompaniesHouseCompanies;
use App\Enums\DumpStatus;
use App\Models\Dump;

it('downloads the free company data product into a companies house jsonl dump', function (): void {
    fakeCompaniesHouseCompanies();

    $dump = resolve(FetchCompaniesHouseCompanies::class)->handle(400);

    expect($dump->source)->toBe('companies_house')
        ->and($dump->dataset)->toBe('ch_companies')
        ->and($dump->status)->toBe(DumpStatus::Pending)
        ->and($dump->record_limit)->toBe(400)
        ->and($dump->attribution)->toContain('Companies House')
        ->and($dump->attribution)->toContain('public register')
        ->and($dump->attribution)->not->toContain('OpenSanctions')
        ->and($dump->attribution)->not->toContain('OGL')
        ->and(is_file((string) $dump->path))->toBeTrue()
        ->and(str_ends_with((string) $dump->path, '.jsonl'))->toBeTrue()
        ->and(is_file(storage_path('app/dumps/'.$dump->id.'.zip')))->toBeFalse()
        ->and(is_file(storage_path('app/dumps/'.$dump->id.'.csv')))->toBeFalse();

    $contents = (string) file_get_contents((string) $dump->path);

    expect($contents)->toContain('"list":"ch_companies"')
        ->and($contents)->toContain('00000006')
        ->and($contents)->toContain('EXAMPLE ONE LTD');
});

it('marks the dump failed when the company product download is not successful', function (): void {
    fakeCompaniesHouseCompanies(403);

    expect(fn () => resolve(FetchCompaniesHouseCompanies::class)->handle(10))
        ->toThrow(RuntimeException::class);

    $dump = Dump::query()->first();

    expect($dump)->not->toBeNull()
        ->and($dump?->status)->toBe(DumpStatus::Failed)
        ->and($dump?->error)->toContain('HTTP 403');
});

it('converts only two company records when the limit is two', function (): void {
    fakeCompaniesHouseCompanies();

    $dump = resolve(FetchCompaniesHouseCompanies::class)->handle(2);
    $lines = array_values(array_filter(explode("\n", mb_trim((string) file_get_contents((string) $dump->path)))));

    expect($lines)->toHaveCount(2)
        ->and($lines[0])->toContain('00000006')
        ->and($lines[1])->toContain('SC123456')
        ->and(implode("\n", $lines))->not->toContain('12345678');
});

it('marks the dump failed when the downloaded company file cannot be converted', function (): void {
    fakeCompaniesHouseCompanies('not a companies house csv');

    expect(fn () => resolve(FetchCompaniesHouseCompanies::class)->handle(10))
        ->toThrow(RuntimeException::class, 'Companies House company CSV is missing the CompanyNumber header.');

    expect(Dump::query()->first()?->status)->toBe(DumpStatus::Failed)
        ->and(Dump::query()->first()?->path)->not->toBeNull()
        ->and(is_file((string) Dump::query()->first()?->path))->toBeFalse();
});

it('aborts before download when free disk is below the companies house budget', function (): void {
    config(['graph.companies_house.min_free_bytes' => PHP_INT_MAX]);

    fakeCompaniesHouseCompanies();

    expect(fn () => resolve(FetchCompaniesHouseCompanies::class)->handle(10))
        ->toThrow(RuntimeException::class, 'Not enough free disk for Companies House ingest');

    expect(Dump::query()->count())->toBe(0);
});

it('downloads a csv override without keeping the raw file', function (): void {
    config(['graph.companies_house.ch_companies.url' => 'https://download.companieshouse.gov.uk/basic.csv']);

    Illuminate\Support\Facades\Http::fake([
        'https://download.companieshouse.gov.uk/basic.csv' => Illuminate\Support\Facades\Http::response(
            file_get_contents(base_path('tests/Fixtures/companies-house/basic-company-data.sample.csv')),
            200,
        ),
    ]);

    $dump = resolve(FetchCompaniesHouseCompanies::class)->handle(1);

    expect($dump->status)->toBe(DumpStatus::Pending)
        ->and((string) file_get_contents((string) $dump->path))->toContain('00000006')
        ->and(is_file(storage_path('app/dumps/'.$dump->id.'.csv')))->toBeFalse();
});
