<?php

declare(strict_types=1);

use App\Actions\FetchCompaniesHouseProduct;
use App\Actions\IngestCompaniesHouseDump;
use App\Enums\DumpStatus;
use App\Enums\GraphNodeLabel;
use App\Graph\GraphClient;
use App\Models\Dump;

it('ingests only two companies house rows when the limit is two', function (): void {
    fakeCompaniesHouseCompanies();

    $dump = resolve(IngestCompaniesHouseDump::class)->handle('ch_companies', 2);

    expect($dump->status)->toBe(DumpStatus::Completed)
        ->and($dump->source)->toBe('companies_house')
        ->and($dump->dataset)->toBe('ch_companies')
        ->and($dump->entities_read)->toBe(2)
        ->and($dump->path)->toBeNull()
        ->and($dump->attribution)->toContain('Companies House')
        ->and(is_file(storage_path('app/dumps/'.$dump->id.'.jsonl')))->toBeFalse();

    $graph = app(GraphClient::class);

    expect(collect($graph->search('EXAMPLE ONE LTD', 5))->contains(
        fn (array $hit): bool => $hit['label'] === GraphNodeLabel::Organization->value,
    ))->toBeTrue()
        ->and(collect($graph->search('EXAMPLE TWO LTD', 5))->isNotEmpty())->toBeTrue()
        ->and(collect($graph->search('EXAMPLE THREE LTD', 5))->isEmpty())->toBeTrue()
        ->and(collect($graph->search('EXAMPLE ONE LTD', 5))->contains(
            fn (array $hit): bool => $hit['label'] === GraphNodeLabel::Person->value,
        ))->toBeFalse();
});

it('rejects an unpublished companies house product', function (): void {
    expect(fn () => resolve(IngestCompaniesHouseDump::class)->handle('gb_coh_psc', 2))
        ->toThrow(RuntimeException::class)
        ->and(Dump::query()->count())->toBe(0);
});

it('rejects a blank companies house product or a missing allow-list', function (): void {
    expect(fn () => resolve(FetchCompaniesHouseProduct::class)->handle('   ', 1))
        ->toThrow(RuntimeException::class);

    config(['graph.companies_house.products' => ['ch_companies', '', 2]]);

    fakeCompaniesHouseCompanies();

    expect(resolve(FetchCompaniesHouseProduct::class)->handle('ch_companies', 1)->dataset)->toBe('ch_companies');

    config(['graph.companies_house.products' => null]);

    expect(fn () => resolve(FetchCompaniesHouseProduct::class)->handle('ch_companies', 1))
        ->toThrow(RuntimeException::class);
});

it('ingests a single psc part when the snapshot only publishes one file', function (): void {
    Illuminate\Support\Facades\Http::fake([
        'https://download.companieshouse.gov.uk/en_pscdata.html' => Illuminate\Support\Facades\Http::response(
            companiesHousePscIndexHtml('psc-snapshot-2026-09-15_1of1.zip'),
            200,
        ),
        'https://download.companieshouse.gov.uk/psc-snapshot-2026-09-15_1of1.zip' => Illuminate\Support\Facades\Http::response(
            companiesHouseZip(
                (string) file_get_contents(base_path('tests/Fixtures/companies-house/psc.part2.sample.jsonl')),
                'psc-snapshot-2026-09-15_1of1.json',
            ),
            200,
        ),
    ]);

    $dump = resolve(IngestCompaniesHouseDump::class)->handle('ch_psc', 1);

    expect($dump->status)->toBe(DumpStatus::Completed)
        ->and(Dump::query()->count())->toBe(1);
});

it('rejects an allow-listed companies house product that is not implemented', function (): void {
    config(['graph.companies_house.products' => ['ch_companies', 'ch_psc', 'nope']]);

    expect(fn () => resolve(FetchCompaniesHouseProduct::class)->handle('nope', 1))
        ->toThrow(RuntimeException::class);

    expect(fn () => resolve(FetchCompaniesHouseProduct::class)->handle('ch_psc', 1))
        ->toThrow(RuntimeException::class, 'PSC fetch requires a 1-based --part');
});
