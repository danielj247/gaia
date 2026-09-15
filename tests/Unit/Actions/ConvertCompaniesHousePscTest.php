<?php

declare(strict_types=1);

use App\Actions\ConvertCompaniesHouseCsv;
use App\Actions\ConvertCompaniesHousePsc;
use App\Actions\FetchCompaniesHousePsc;
use App\Actions\IngestCompaniesHouseDump;
use App\Actions\ResolveCompaniesHouseSnapshot;
use App\Models\Dump;
use Illuminate\Support\Facades\Http;

it('fails conversion when the psc source is missing or the destination cannot be written', function (): void {
    $directory = storage_path('app/dumps');

    expect(fn () => resolve(ConvertCompaniesHousePsc::class)->handle(
        $directory.'/missing.jsonl',
        $directory.'/out.jsonl',
    ))->toThrow(RuntimeException::class, 'Unable to read the Companies House PSC file.');

    expect(fn () => resolve(ConvertCompaniesHousePsc::class)->handle(
        base_path('tests/Fixtures/companies-house/psc.sample.jsonl'),
        $directory,
    ))->toThrow(RuntimeException::class, 'Unable to write the Companies House PSC JSON lines.');
});

it('fails when a psc zip has no json or the jsonl file cannot be opened', function (): void {
    $directory = storage_path('app/dumps');

    if (! is_dir($directory)) {
        mkdir($directory, 0755, true);
    }

    $emptyZip = $directory.'/empty-psc.zip';
    file_put_contents($emptyZip, companiesHouseZip('not json', 'readme.md'));

    expect(fn () => resolve(ConvertCompaniesHousePsc::class)->handle($emptyZip, $directory.'/empty-psc.jsonl'))
        ->toThrow(RuntimeException::class, 'Companies House PSC zip does not contain a JSON file.');

    $blocked = $directory.'/blocked-psc.jsonl';
    file_put_contents($blocked, '');
    chmod($blocked, 0444);

    try {
        expect(fn () => resolve(ConvertCompaniesHousePsc::class)->handle(
            base_path('tests/Fixtures/companies-house/psc.sample.jsonl'),
            $blocked,
        ))->toThrow(RuntimeException::class, 'Unable to write the Companies House PSC JSON lines.');
    } finally {
        chmod($blocked, 0644);
        unlink($blocked);
    }
});

it('skips blank, invalid and non-graph psc lines when converting a raw jsonl file', function (): void {
    $directory = storage_path('app/dumps');

    if (! is_dir($directory)) {
        mkdir($directory, 0755, true);
    }

    $source = $directory.'/psc-raw.jsonl';
    $jsonl = $directory.'/psc-converted.jsonl';
    file_put_contents($source, "\nnot-json\n[]\n".file_get_contents(base_path('tests/Fixtures/companies-house/psc.sample.jsonl')));

    resolve(ConvertCompaniesHousePsc::class)->handle($source, $jsonl);

    $contents = (string) file_get_contents($jsonl);

    expect($contents)->toContain('ch-psc:notifAda1')
        ->and($contents)->toContain('ch-psc:notifCorp1')
        ->and($contents)->not->toContain('super-secure-person-with-significant-control')
        ->and($contents)->not->toContain('persons-with-significant-control-statement');
});

it('skips graph-shaped psc rows that have no company number or notification id', function (): void {
    $directory = storage_path('app/dumps');

    if (! is_dir($directory)) {
        mkdir($directory, 0755, true);
    }

    $source = $directory.'/psc-noid.jsonl';
    $jsonl = $directory.'/psc-noid-out.jsonl';
    file_put_contents($source, implode("\n", [
        '{"data":{"kind":"individual-person-with-significant-control","name":"No Ids"}}',
        '{"company_number":"00000006","data":{"kind":"individual-person-with-significant-control","name":"No Source"}}',
        '{"data":{"kind":"individual-person-with-significant-control","etag":"only-etag","name":"No Company"}}',
        '',
    ]));

    resolve(ConvertCompaniesHousePsc::class)->handle($source, $jsonl);

    expect(mb_trim((string) file_get_contents($jsonl)))->toBe('');
});

it('fails when a zip member cannot be streamed', function (): void {
    $directory = storage_path('app/dumps');

    if (! is_dir($directory)) {
        mkdir($directory, 0755, true);
    }

    $companyZip = $directory.'/encrypted-company.zip';
    $company = new ZipArchive;
    $company->open($companyZip, ZipArchive::CREATE);
    $company->addFromString('BasicCompanyData.csv', "CompanyNumber,CompanyName\n6,SECRET LTD\n");
    $company->setEncryptionName('BasicCompanyData.csv', ZipArchive::EM_AES_256, 'secret');
    $company->close();

    expect(fn () => resolve(ConvertCompaniesHouseCsv::class)->handle($companyZip, $directory.'/encrypted-company.jsonl'))
        ->toThrow(RuntimeException::class, 'Unable to read the Companies House company data file.');

    $pscZip = $directory.'/encrypted-psc.zip';
    $psc = new ZipArchive;
    $psc->open($pscZip, ZipArchive::CREATE);
    $psc->addFromString('snapshot.json', "{\"company_number\":\"00000006\"}\n");
    $psc->setEncryptionName('snapshot.json', ZipArchive::EM_AES_256, 'secret');
    $psc->close();

    expect(fn () => resolve(ConvertCompaniesHousePsc::class)->handle($pscZip, $directory.'/encrypted-psc.jsonl'))
        ->toThrow(RuntimeException::class, 'Unable to read the Companies House PSC file.');
});

it('fails when a psc zip cannot be opened and converts a json zip detected by magic bytes', function (): void {
    $directory = storage_path('app/dumps');

    if (! is_dir($directory)) {
        mkdir($directory, 0755, true);
    }

    $garbage = $directory.'/garbage-psc.zip';
    file_put_contents($garbage, 'nope');

    expect(fn () => resolve(ConvertCompaniesHousePsc::class)->handle($garbage, $directory.'/garbage-psc.jsonl'))
        ->toThrow(RuntimeException::class, 'Unable to read the Companies House PSC file.');

    $bin = $directory.'/psc-magic.bin';
    file_put_contents($bin, companiesHouseZip(
        (string) file_get_contents(base_path('tests/Fixtures/companies-house/psc.part2.sample.jsonl')),
        'snapshot.txt',
    ));
    $jsonl = $directory.'/psc-magic.jsonl';

    resolve(ConvertCompaniesHousePsc::class)->handle($bin, $jsonl);

    expect((string) file_get_contents($jsonl))->toContain('Bea Two');

    $emptyZip = $directory.'/empty-psc.bin';
    file_put_contents($emptyZip, "PK\x05\x06".str_repeat("\x00", 18));

    expect(fn () => resolve(ConvertCompaniesHousePsc::class)->handle($emptyZip, $directory.'/empty-psc.jsonl'))
        ->toThrow(RuntimeException::class, 'Companies House PSC zip does not contain a JSON file.');

    $readme = $directory.'/readme-psc.zip';
    file_put_contents($readme, companiesHouseZip('not json', 'readme.md'));

    expect(fn () => resolve(ConvertCompaniesHousePsc::class)->handle($readme, $directory.'/readme-psc.jsonl'))
        ->toThrow(RuntimeException::class, 'Companies House PSC zip does not contain a JSON file.');
});

it('marks the dump failed when a downloaded psc part cannot be converted', function (): void {
    Http::fake([
        'https://download.companieshouse.gov.uk/en_pscdata.html' => Http::response(
            companiesHousePscIndexHtml('psc-snapshot-2026-09-15_1of1.zip'),
            200,
        ),
        'https://download.companieshouse.gov.uk/psc-snapshot-2026-09-15_1of1.zip' => Http::response('nope', 200),
    ]);

    expect(fn () => resolve(FetchCompaniesHousePsc::class)->handle(10, 1))
        ->toThrow(RuntimeException::class, 'Unable to read the Companies House PSC file.');

    expect(Dump::query()->first()?->status)->toBe(App\Enums\DumpStatus::Failed);
});

it('resolves every psc part when no part is requested', function (): void {
    Http::fake([
        'https://download.companieshouse.gov.uk/en_pscdata.html' => Http::response(
            companiesHousePscIndexHtml(
                'psc-snapshot-2026-09-15_2of2.zip',
                'psc-snapshot-2026-09-15_1of2.zip',
            ),
            200,
        ),
    ]);

    $snapshots = resolve(ResolveCompaniesHouseSnapshot::class)->handle('ch_psc');

    expect($snapshots)->toHaveCount(2)
        ->and($snapshots[0]['part'])->toBe(1)
        ->and($snapshots[1]['part'])->toBe(2);
});

it('rejects a psc part that is not on the current snapshot and a fetch without a part', function (): void {
    Http::fake([
        'https://download.companieshouse.gov.uk/en_pscdata.html' => Http::response(
            companiesHousePscIndexHtml('psc-snapshot-2026-09-15_1of2.zip'),
            200,
        ),
    ]);

    expect(fn () => resolve(ResolveCompaniesHouseSnapshot::class)->handle('ch_psc', 2))
        ->toThrow(RuntimeException::class, 'Companies House PSC part 2 is not on the current snapshot.');

    expect(fn () => resolve(FetchCompaniesHousePsc::class)->handle(1))
        ->toThrow(RuntimeException::class, 'PSC fetch requires a 1-based --part')
        ->and(fn () => resolve(FetchCompaniesHousePsc::class)->handle(1, 0))
        ->toThrow(RuntimeException::class, 'PSC fetch requires a 1-based --part');
});

it('aborts a full psc walk when there is not about 24gb free', function (): void {
    config(['graph.companies_house.psc_min_free_bytes' => PHP_INT_MAX]);

    fakeCompaniesHousePscParts();

    expect(fn () => resolve(IngestCompaniesHouseDump::class)->handle('ch_psc', null))
        ->toThrow(RuntimeException::class, 'Not enough free disk for Companies House ingest');

    expect(Dump::query()->count())->toBe(0);
});
