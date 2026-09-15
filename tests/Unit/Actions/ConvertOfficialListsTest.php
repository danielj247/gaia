<?php

declare(strict_types=1);

use App\Actions\ConvertEuFsfXml;
use App\Actions\ConvertOfacSdnXml;
use App\Actions\ConvertUkSanctionsCsv;
use App\Actions\DownloadOfficialSource;
use App\Enums\DumpStatus;
use App\Graph\UkSanctionsFields;
use App\Models\Dump;
use Illuminate\Support\Facades\Http;

it('converts a uksl csv with blank lines and nameless extra rows', function (): void {
    $directory = storage_path('app/dumps');

    if (! is_dir($directory)) {
        mkdir($directory, 0755, true);
    }

    $csv = $directory.'/convert-uksl.csv';
    $jsonl = $directory.'/convert-uksl.jsonl';

    file_put_contents($csv, "\n".file_get_contents(base_path('tests/Fixtures/official/uksl.sample.csv'))."\n04/08/2026,GBR001,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,,\n");

    resolve(ConvertUkSanctionsCsv::class)->handle($csv, $jsonl);

    expect(file_get_contents($jsonl))->toContain('GBR001')
        ->and(file_get_contents($jsonl))->toContain('Ada Example');
});

it('fails conversion when the source file is missing', function (): void {
    expect(fn () => resolve(ConvertUkSanctionsCsv::class)->handle(storage_path('app/dumps/missing.csv'), storage_path('app/dumps/out.jsonl')))
        ->toThrow(RuntimeException::class, 'Unable to read the UK Sanctions List CSV.');

    expect(fn () => resolve(ConvertOfacSdnXml::class)->handle(storage_path('app/dumps/missing.xml'), storage_path('app/dumps/out.jsonl')))
        ->toThrow(RuntimeException::class, 'Unable to read the OFAC SDN XML.');

    expect(fn () => resolve(ConvertEuFsfXml::class)->handle(storage_path('app/dumps/missing.xml'), storage_path('app/dumps/out.jsonl')))
        ->toThrow(RuntimeException::class, 'Unable to read the EU FSF XML.');
});

it('fails conversion when the jsonl destination cannot be written', function (): void {
    $directory = storage_path('app/dumps');

    expect(fn () => resolve(ConvertUkSanctionsCsv::class)->handle(
        base_path('tests/Fixtures/official/uksl.sample.csv'),
        $directory,
    ))->toThrow(RuntimeException::class, 'Unable to write the UK Sanctions List JSON lines.');

    expect(fn () => resolve(ConvertOfacSdnXml::class)->handle(
        base_path('tests/Fixtures/official/ofac_sdn.sample.xml'),
        $directory,
    ))->toThrow(RuntimeException::class, 'Unable to write the OFAC SDN JSON lines.');

    expect(fn () => resolve(ConvertEuFsfXml::class)->handle(
        base_path('tests/Fixtures/official/eu_fsf.sample.xml'),
        $directory,
    ))->toThrow(RuntimeException::class, 'Unable to write the EU FSF JSON lines.');
});

it('reads alias-only uksl rows and integer-looking field values', function (): void {
    expect(UkSanctionsFields::aliases([
        'Name type' => 'Alias',
        'Name 6' => 'A. Example',
    ]))->toBe(['A. Example'])
        ->and(UkSanctionsFields::aliases([
            'Name type' => 'Alias',
        ]))->toBe([])
        ->and(UkSanctionsFields::uniqueId(['Unique ID' => 1001]))->toBe('1001');
});

it('fails conversion when the jsonl file cannot be opened for writing', function (): void {
    $directory = storage_path('app/dumps');

    if (! is_dir($directory)) {
        mkdir($directory, 0755, true);
    }

    $blocked = $directory.'/blocked.jsonl';
    file_put_contents($blocked, '');
    chmod($blocked, 0444);

    try {
        expect(fn () => resolve(ConvertUkSanctionsCsv::class)->handle(
            base_path('tests/Fixtures/official/uksl.sample.csv'),
            $blocked,
        ))->toThrow(RuntimeException::class, 'Unable to write the UK Sanctions List JSON lines.');

        expect(fn () => resolve(ConvertOfacSdnXml::class)->handle(
            base_path('tests/Fixtures/official/ofac_sdn.sample.xml'),
            $blocked,
        ))->toThrow(RuntimeException::class, 'Unable to write the OFAC SDN JSON lines.');

        expect(fn () => resolve(ConvertEuFsfXml::class)->handle(
            base_path('tests/Fixtures/official/eu_fsf.sample.xml'),
            $blocked,
        ))->toThrow(RuntimeException::class, 'Unable to write the EU FSF JSON lines.');
    } finally {
        chmod($blocked, 0644);
        unlink($blocked);
    }
});

it('fails conversion when official xml does not look like xml', function (): void {
    $directory = storage_path('app/dumps');

    if (! is_dir($directory)) {
        mkdir($directory, 0755, true);
    }

    $garbage = $directory.'/garbage.xml';
    file_put_contents($garbage, 'nope');

    expect(fn () => resolve(ConvertOfacSdnXml::class)->handle($garbage, $directory.'/ofac.jsonl'))
        ->toThrow(RuntimeException::class, 'Unable to read the OFAC SDN XML.');

    expect(fn () => resolve(ConvertEuFsfXml::class)->handle($garbage, $directory.'/eu.jsonl'))
        ->toThrow(RuntimeException::class, 'Unable to read the EU FSF XML.');
});

it('fails when official dump storage cannot be created', function (): void {
    $root = storage_path('blocked-'.uniqid());
    mkdir($root, 0755, true);
    chmod($root, 0555);
    app()->useStoragePath($root);

    Http::fake(['*' => Http::response('x', 200)]);

    try {
        expect(fn () => resolve(DownloadOfficialSource::class)->handle(
            'uksl',
            1,
            'https://example.test/list.csv',
            'csv',
        ))->toThrow(RuntimeException::class, 'Unable to create dump storage.');

        expect(Dump::query()->first()?->status)->toBe(DumpStatus::Failed);
    } finally {
        app()->useStoragePath(base_path('storage'));
        chmod($root, 0755);
        rmdir($root);
    }
});
