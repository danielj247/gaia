<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Sleep;
use Illuminate\Support\Str;
use Tests\TestCase;

pest()->tia()
    ->locally();

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->beforeEach(function (): void {
        Str::createRandomStringsNormally();
        Str::createUuidsNormally();
        Http::preventStrayRequests();
        Process::preventStrayProcesses();
        Sleep::fake();

        $this->freezeTime();
    })
    ->in('Browser', 'Feature', 'Unit');

function companiesHouseIndexHtml(string $filename): string
{
    return '<ul><li><a href="'.$filename.'">'.$filename.' (1Mb)</a></li></ul>';
}

function companiesHousePscIndexHtml(string ...$filenames): string
{
    $items = '';

    foreach ($filenames as $filename) {
        $items .= '<li><a href="'.$filename.'">'.$filename.' (1Mb)</a></li>';
    }

    return '<h2>PSC data as one file:</h2><ul><li><a href="persons-with-significant-control-snapshot-2026-09-15.zip">persons-with-significant-control-snapshot-2026-09-15.zip</a></li></ul><h2>PSC data as multiple files:</h2><ul>'.$items.'</ul>';
}

function companiesHouseZip(string $contents, string $innerName = 'BasicCompanyDataAsOneFile-2026-09-01.csv'): string
{
    $path = sys_get_temp_dir().'/ch-'.uniqid('', true).'.zip';
    $zip = new ZipArchive;
    $zip->open($path, ZipArchive::CREATE);
    $zip->addFromString($innerName, $contents);
    $zip->close();

    $bytes = (string) file_get_contents($path);
    unlink($path);

    return $bytes;
}

function fakeCompaniesHousePscParts(): void
{
    $partOne = (string) file_get_contents(base_path('tests/Fixtures/companies-house/psc.sample.jsonl'));
    $partTwo = (string) file_get_contents(base_path('tests/Fixtures/companies-house/psc.part2.sample.jsonl'));

    Http::fake([
        'https://download.companieshouse.gov.uk/en_pscdata.html' => Http::response(
            companiesHousePscIndexHtml(
                'psc-snapshot-2026-09-15_1of2.zip',
                'psc-snapshot-2026-09-15_2of2.zip',
            ),
            200,
        ),
        'https://download.companieshouse.gov.uk/psc-snapshot-2026-09-15_1of2.zip' => Http::response(
            companiesHouseZip($partOne, 'psc-snapshot-2026-09-15_1of2.json'),
            200,
        ),
        'https://download.companieshouse.gov.uk/psc-snapshot-2026-09-15_2of2.zip' => Http::response(
            companiesHouseZip($partTwo, 'psc-snapshot-2026-09-15_2of2.json'),
            200,
        ),
    ]);
}

function fakeCompaniesHouseCompanies(string|int|null $body = null, int $status = 200): void
{
    $csv = is_string($body) ? $body : (string) file_get_contents(base_path('tests/Fixtures/companies-house/basic-company-data.sample.csv'));

    Http::fake([
        'https://download.companieshouse.gov.uk/en_output.html' => Http::response(
            companiesHouseIndexHtml('BasicCompanyDataAsOneFile-2026-09-01.zip'),
            200,
        ),
        'https://download.companieshouse.gov.uk/BasicCompanyDataAsOneFile-2026-09-01.zip' => Http::response(
            is_int($body) ? 'nope' : companiesHouseZip($csv),
            is_int($body) ? $body : $status,
        ),
    ]);
}
