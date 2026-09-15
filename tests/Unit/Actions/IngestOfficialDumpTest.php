<?php

declare(strict_types=1);

use App\Actions\IngestOfficialDump;
use App\Enums\DumpStatus;
use App\Enums\GraphNodeLabel;
use App\Graph\GraphClient;
use App\Models\Dump;
use App\Models\DumpError;
use Illuminate\Support\Facades\Http;

function fakeUkSanctionsList(): void
{
    Http::fake([
        'https://sanctionslist.fcdo.gov.uk/*' => Http::response(
            file_get_contents(base_path('tests/Fixtures/official/uksl.sample.csv')),
            200,
        ),
    ]);
}

it('ingests only two uksl targets when the limit is two', function (): void {
    fakeUkSanctionsList();

    $dump = resolve(IngestOfficialDump::class)->handle('uksl', 2);

    expect($dump->status)->toBe(DumpStatus::Completed)
        ->and($dump->entities_read)->toBe(2)
        ->and($dump->path)->toBeNull()
        ->and($dump->attribution)->toContain('FCDO');

    $graph = app(GraphClient::class);

    expect(collect($graph->search('GBR001', 5))->isNotEmpty())->toBeTrue()
        ->and(collect($graph->search('GBR002', 5))->isNotEmpty())->toBeTrue()
        ->and(collect($graph->search('GBR003', 5))->isEmpty())->toBeTrue();
});

it('records an invalid row as a dump error and does not abort the chunk', function (): void {
    fakeUkSanctionsList();

    $dump = resolve(IngestOfficialDump::class)->handle('uksl', 10);

    expect($dump->status)->toBe(DumpStatus::Completed)
        ->and($dump->errors()->pluck('code')->all())->toContain('missing_id')
        ->and(DumpError::query()->where('dump_id', $dump->id)->exists())->toBeTrue()
        ->and(collect(app(GraphClient::class)->search('GBR003', 5))->contains(
            fn (array $hit): bool => $hit['label'] === GraphNodeLabel::Vessel->value,
        ))->toBeTrue();
});

it('deletes the downloaded file after a successful parse', function (): void {
    fakeUkSanctionsList();

    $dump = resolve(IngestOfficialDump::class)->handle('uksl', 2);
    $stored = storage_path('app/dumps/'.$dump->id.'.jsonl');
    $csv = storage_path('app/dumps/'.$dump->id.'.csv');

    expect($dump->status)->toBe(DumpStatus::Completed)
        ->and($dump->path)->toBeNull()
        ->and(is_file($stored))->toBeFalse()
        ->and(is_file($csv))->toBeFalse();
});

it('ingests a faked ofac sdn dump and deletes the download', function (): void {
    Http::fake([
        'https://sanctionslistservice.ofac.treas.gov/*' => Http::response(
            file_get_contents(base_path('tests/Fixtures/official/ofac_sdn.sample.xml')),
            200,
        ),
    ]);

    $dump = resolve(IngestOfficialDump::class)->handle('ofac_sdn', 2);

    expect($dump->status)->toBe(DumpStatus::Completed)
        ->and($dump->dataset)->toBe('ofac_sdn')
        ->and($dump->entities_read)->toBe(2)
        ->and($dump->path)->toBeNull()
        ->and($dump->attribution)->toContain('OFAC')
        ->and(is_file(storage_path('app/dumps/'.$dump->id.'.xml')))->toBeFalse()
        ->and(is_file(storage_path('app/dumps/'.$dump->id.'.jsonl')))->toBeFalse()
        ->and(collect(app(GraphClient::class)->search('Ada Example', 5))->isNotEmpty())->toBeTrue();
});

it('ingests a faked eu fsf dump and deletes the download', function (): void {
    config(['graph.official.eu_fsf.token' => 'test-token']);

    Http::fake([
        'https://webgate.ec.europa.eu/*' => Http::response(
            file_get_contents(base_path('tests/Fixtures/official/eu_fsf.sample.xml')),
            200,
        ),
    ]);

    $dump = resolve(IngestOfficialDump::class)->handle('eu_fsf', 2);

    expect($dump->status)->toBe(DumpStatus::Completed)
        ->and($dump->dataset)->toBe('eu_fsf')
        ->and($dump->entities_read)->toBe(2)
        ->and($dump->path)->toBeNull()
        ->and($dump->attribution)->toContain('European Commission')
        ->and(collect(app(GraphClient::class)->search('Ada Example', 5))->isNotEmpty())->toBeTrue();
});

it('completes an official uksl dump that contains no designations', function (): void {
    Http::fake([
        'https://sanctionslist.fcdo.gov.uk/*' => Http::response(
            "Report Date: 11-Sep-2026\nLast Updated,Unique ID,Name 6\n",
            200,
        ),
    ]);

    $dump = resolve(IngestOfficialDump::class)->handle('uksl', 400);

    expect($dump->status)->toBe(DumpStatus::Completed)
        ->and($dump->entities_read)->toBe(0)
        ->and($dump->path)->toBeNull()
        ->and(is_file(storage_path('app/dumps/'.$dump->id.'.jsonl')))->toBeFalse();
});

it('rejects an unpublished official list', function (): void {
    expect(fn () => resolve(IngestOfficialDump::class)->handle('opensanctions', 2))
        ->toThrow(RuntimeException::class)
        ->and(Dump::query()->count())->toBe(0);
});
