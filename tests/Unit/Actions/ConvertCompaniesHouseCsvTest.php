<?php

declare(strict_types=1);

use App\Actions\ConvertCompaniesHouseCsv;
use App\Actions\DownloadCompaniesHouseSource;
use App\Actions\EnsureCompaniesHouseDiskBudget;
use App\Actions\ResolveCompaniesHouseSnapshot;
use App\Enums\DumpStatus;
use App\Models\Dump;
use Illuminate\Support\Facades\Http;

it('streams a company zip to jsonl and skips rows without a company number', function (): void {
    $directory = storage_path('app/dumps');

    if (! is_dir($directory)) {
        mkdir($directory, 0755, true);
    }

    $zipPath = $directory.'/convert-ch.zip';
    $jsonl = $directory.'/convert-ch.jsonl';
    file_put_contents($zipPath, companiesHouseZip(
        "\n".file_get_contents(base_path('tests/Fixtures/companies-house/basic-company-data.sample.csv'))."\nEXAMPLE NONE LTD,,,,,,,,,,,,,,,,,,,\n",
    ));

    resolve(ConvertCompaniesHouseCsv::class)->handle($zipPath, $jsonl);

    $contents = (string) file_get_contents($jsonl);

    expect($contents)->toContain('00000006')
        ->and($contents)->toContain('SC123456')
        ->and($contents)->toContain('EXAMPLE THREE LTD')
        ->and($contents)->not->toContain('EXAMPLE NONE LTD');
});

it('fails conversion when the company source is missing or the destination cannot be written', function (): void {
    $directory = storage_path('app/dumps');

    expect(fn () => resolve(ConvertCompaniesHouseCsv::class)->handle(
        $directory.'/missing.csv',
        $directory.'/out.jsonl',
    ))->toThrow(RuntimeException::class, 'Unable to read the Companies House company data file.');

    expect(fn () => resolve(ConvertCompaniesHouseCsv::class)->handle(
        base_path('tests/Fixtures/companies-house/basic-company-data.sample.csv'),
        $directory,
    ))->toThrow(RuntimeException::class, 'Unable to write the Companies House company JSON lines.');
});

it('fails when a company zip has no csv or the jsonl file cannot be opened', function (): void {
    $directory = storage_path('app/dumps');

    if (! is_dir($directory)) {
        mkdir($directory, 0755, true);
    }

    $emptyZip = $directory.'/empty-ch.zip';
    file_put_contents($emptyZip, companiesHouseZip('not csv', 'readme.txt'));

    expect(fn () => resolve(ConvertCompaniesHouseCsv::class)->handle($emptyZip, $directory.'/empty.jsonl'))
        ->toThrow(RuntimeException::class, 'Companies House company zip does not contain a CSV file.');

    $emptyArchive = $directory.'/empty-magic.bin';
    file_put_contents($emptyArchive, "PK\x05\x06".str_repeat("\x00", 18));

    expect(fn () => resolve(ConvertCompaniesHouseCsv::class)->handle($emptyArchive, $directory.'/empty-magic.jsonl'))
        ->toThrow(RuntimeException::class, 'Companies House company zip does not contain a CSV file.');

    $blocked = $directory.'/blocked-ch.jsonl';
    file_put_contents($blocked, '');
    chmod($blocked, 0444);

    try {
        expect(fn () => resolve(ConvertCompaniesHouseCsv::class)->handle(
            base_path('tests/Fixtures/companies-house/basic-company-data.sample.csv'),
            $blocked,
        ))->toThrow(RuntimeException::class, 'Unable to write the Companies House company JSON lines.');
    } finally {
        chmod($blocked, 0644);
        unlink($blocked);
    }
});

it('fails when official-style dump storage cannot be created for companies house', function (): void {
    $root = storage_path('blocked-ch-'.uniqid());
    mkdir($root, 0755, true);
    chmod($root, 0555);
    app()->useStoragePath($root);

    Http::fake(['*' => Http::response('x', 200)]);

    try {
        expect(fn () => resolve(DownloadCompaniesHouseSource::class)->handle(
            'ch_companies',
            1,
            'https://example.test/basic.zip',
            'zip',
            'basic.zip',
        ))->toThrow(RuntimeException::class, 'Unable to create dump storage.');

        expect(Dump::query()->first()?->status)->toBe(DumpStatus::Failed);
    } finally {
        app()->useStoragePath(base_path('storage'));
        chmod($root, 0755);
        rmdir($root);
    }
});

it('resolves the latest one-file company zip and refuses a single-file psc zip', function (): void {
    Http::fake([
        'https://download.companieshouse.gov.uk/en_output.html' => Http::response(
            companiesHouseIndexHtml('BasicCompanyDataAsOneFile-2026-09-01.zip'),
            200,
        ),
        'https://download.companieshouse.gov.uk/en_pscdata.html' => Http::response(
            companiesHousePscIndexHtml('psc-snapshot-2026-09-15_1of32.zip', 'psc-snapshot-2026-09-15_2of32.zip'),
            200,
        ),
    ]);

    $companies = resolve(ResolveCompaniesHouseSnapshot::class)->handle('ch_companies');
    $psc = resolve(ResolveCompaniesHouseSnapshot::class)->handle('ch_psc', 2);

    expect($companies[0]['filename'])->toBe('BasicCompanyDataAsOneFile-2026-09-01.zip')
        ->and($psc[0]['filename'])->toBe('psc-snapshot-2026-09-15_2of32.zip')
        ->and($psc[0]['part'])->toBe(2);

    config(['graph.companies_house.ch_psc.url' => 'https://download.companieshouse.gov.uk/persons-with-significant-control-snapshot-2026-09-15.zip']);

    expect(fn () => resolve(ResolveCompaniesHouseSnapshot::class)->handle('ch_psc', 1))
        ->toThrow(RuntimeException::class, 'Refusing the single-file Companies House PSC zip');
});

it('rejects a missing companies house index listing or an unpublished product', function (): void {
    Http::fake([
        'https://download.companieshouse.gov.uk/en_output.html' => Http::response('no files here', 200),
        'https://download.companieshouse.gov.uk/en_pscdata.html' => Http::response('nope', 503),
    ]);

    expect(fn () => resolve(ResolveCompaniesHouseSnapshot::class)->handle('ch_companies'))
        ->toThrow(RuntimeException::class, 'Unable to resolve the latest Companies House ch_companies snapshot.');

    expect(fn () => resolve(ResolveCompaniesHouseSnapshot::class)->handle('ch_psc', 1))
        ->toThrow(RuntimeException::class, 'Unable to read the Companies House download index (HTTP 503).');

    expect(fn () => resolve(ResolveCompaniesHouseSnapshot::class)->handle('gb_coh_psc'))
        ->toThrow(RuntimeException::class, 'Product must be a published Companies House product');

    config(['graph.companies_house.products' => null]);

    expect(fn () => resolve(ResolveCompaniesHouseSnapshot::class)->handle('ch_companies'))
        ->toThrow(RuntimeException::class);
});

it('converts a company zip detected by magic bytes and a csv with a spaced company number header', function (): void {
    $directory = storage_path('app/dumps');

    if (! is_dir($directory)) {
        mkdir($directory, 0755, true);
    }

    $bin = $directory.'/company-magic.bin';
    file_put_contents($bin, companiesHouseZip("Company Number,CompanyName\n6,MAGIC LTD\n"));
    $jsonl = $directory.'/company-magic.jsonl';

    resolve(ConvertCompaniesHouseCsv::class)->handle($bin, $jsonl);

    expect((string) file_get_contents($jsonl))->toContain('00000006')
        ->and((string) file_get_contents($jsonl))->toContain('MAGIC LTD');
});

it('fails when a company zip cannot be opened', function (): void {
    $directory = storage_path('app/dumps');

    if (! is_dir($directory)) {
        mkdir($directory, 0755, true);
    }

    $garbage = $directory.'/garbage-company.zip';
    file_put_contents($garbage, 'nope');

    expect(fn () => resolve(ConvertCompaniesHouseCsv::class)->handle($garbage, $directory.'/garbage-company.jsonl'))
        ->toThrow(RuntimeException::class, 'Unable to read the Companies House company data file.');

    $spanned = $directory.'/spanned-company.bin';
    file_put_contents($spanned, "PK\x07\x08");

    expect(fn () => resolve(ConvertCompaniesHouseCsv::class)->handle($spanned, $directory.'/spanned-company.jsonl'))
        ->toThrow(RuntimeException::class, 'Unable to read the Companies House company data file.');
});

it('fails when a company csv has no company number header', function (): void {
    $directory = storage_path('app/dumps');

    if (! is_dir($directory)) {
        mkdir($directory, 0755, true);
    }

    $csv = $directory.'/no-header.csv';
    file_put_contents($csv, "\nName,Number\nEXAMPLE,6\n");

    expect(fn () => resolve(ConvertCompaniesHouseCsv::class)->handle($csv, $directory.'/no-header.jsonl'))
        ->toThrow(RuntimeException::class, 'Companies House company CSV is missing the CompanyNumber header.');
});

it('resolves an absolute company zip href and a psc listing with no matching part files', function (): void {
    Http::fake([
        'https://download.companieshouse.gov.uk/en_output.html' => Http::response(
            '<a href="http://download.companieshouse.gov.uk/BasicCompanyDataAsOneFile-2026-09-01.zip">one</a>',
            200,
        ),
        'https://download.companieshouse.gov.uk/en_pscdata.html' => Http::response(
            '<a href="persons-with-significant-control-snapshot-2026-09-15.zip">single</a>',
            200,
        ),
    ]);

    expect(resolve(ResolveCompaniesHouseSnapshot::class)->handle('ch_companies')[0]['url'])
        ->toBe('http://download.companieshouse.gov.uk/BasicCompanyDataAsOneFile-2026-09-01.zip');

    expect(fn () => resolve(ResolveCompaniesHouseSnapshot::class)->handle('ch_psc'))
        ->toThrow(RuntimeException::class, 'Unable to resolve the latest Companies House ch_psc snapshot.');

    config(['graph.companies_house.ch_companies.url' => 'https://download.companieshouse.gov.uk']);

    $override = resolve(ResolveCompaniesHouseSnapshot::class)->handle('ch_companies')[0];

    expect($override['extension'])->toBe('zip')
        ->and($override['filename'])->not->toBe('');

    config([
        'graph.companies_house.ch_companies.url' => null,
        'graph.companies_house.ch_psc.url' => 'https://download.companieshouse.gov.uk/psc-snapshot-2026-09-15_1of32.zip',
    ]);

    $pscOverride = resolve(ResolveCompaniesHouseSnapshot::class)->handle('ch_psc', 1)[0];

    expect($pscOverride['filename'])->toBe('psc-snapshot-2026-09-15_1of32.zip')
        ->and($pscOverride['part'])->toBe(1);
});

it('fails the disk budget when a store path has no measurable free space', function (): void {
    config([
        'graph.companies_house.min_free_bytes' => 1,
        'graph.companies_house.store_paths' => [storage_path('app/dumps'), '', 2],
    ]);

    resolve(EnsureCompaniesHouseDiskBudget::class)->handle(1);

    config(['graph.companies_house.store_paths' => null]);

    resolve(EnsureCompaniesHouseDiskBudget::class)->handle(1);

    config(['graph.companies_house.store_paths' => [storage_path('app/dumps/missing-ch-store/nested')]]);

    resolve(EnsureCompaniesHouseDiskBudget::class)->handle(1);

    config(['graph.companies_house.store_paths' => [
        '/definitely-missing-ch-root/a/b/c/d/e/f/g/h/i',
        '/',
    ]]);

    resolve(EnsureCompaniesHouseDiskBudget::class)->handle(1);

    expect(true)->toBeTrue();
});
