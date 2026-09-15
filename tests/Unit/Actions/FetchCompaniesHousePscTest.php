<?php

declare(strict_types=1);

use App\Actions\FetchCompaniesHousePsc;
use App\Actions\IngestCompaniesHouseDump;
use App\Enums\DumpStatus;
use App\Enums\GraphNodeLabel;
use App\Graph\GraphClient;
use App\Models\Dump;
use Illuminate\Support\Facades\Http;

it('downloads one psc zip part into jsonl and deletes the zip', function (): void {
    fakeCompaniesHousePscParts();

    $dump = resolve(FetchCompaniesHousePsc::class)->handle(400, 1);

    expect($dump->source)->toBe('companies_house')
        ->and($dump->dataset)->toBe('ch_psc')
        ->and($dump->status)->toBe(DumpStatus::Pending)
        ->and($dump->attribution)->toContain('Companies House')
        ->and($dump->attribution)->toContain('public register')
        ->and($dump->attribution)->not->toContain('OpenSanctions')
        ->and(str_ends_with((string) $dump->path, '.jsonl'))->toBeTrue()
        ->and(is_file(storage_path('app/dumps/'.$dump->id.'.zip')))->toBeFalse()
        ->and((string) file_get_contents((string) $dump->path))->toContain('ch-psc:notifAda1')
        ->and((string) file_get_contents((string) $dump->path))->not->toContain('totals#persons-of-significant-control-snapshot');
});

it('converts only two psc records when the limit is two', function (): void {
    fakeCompaniesHousePscParts();

    $dump = resolve(FetchCompaniesHousePsc::class)->handle(2, 1);
    $lines = array_values(array_filter(explode("\n", mb_trim((string) file_get_contents((string) $dump->path)))));

    expect($lines)->toHaveCount(2)
        ->and($lines[0])->toContain('notifAda1')
        ->and($lines[1])->toContain('notifCorp1');
});

it('marks the dump failed when the psc part download is not successful', function (): void {
    Http::fake([
        'https://download.companieshouse.gov.uk/en_pscdata.html' => Http::response(
            companiesHousePscIndexHtml('psc-snapshot-2026-09-15_32of32.zip'),
            200,
        ),
        'https://download.companieshouse.gov.uk/psc-snapshot-2026-09-15_32of32.zip' => Http::response('nope', 403),
    ]);

    expect(fn () => resolve(FetchCompaniesHousePsc::class)->handle(10, 32))
        ->toThrow(RuntimeException::class);

    expect(Dump::query()->first()?->status)->toBe(DumpStatus::Failed)
        ->and(Dump::query()->first()?->error)->toContain('HTTP 403');
});

it('downloads the second psc zip part', function (): void {
    fakeCompaniesHousePscParts();

    $dump = resolve(FetchCompaniesHousePsc::class)->handle(400, 2);

    expect((string) file_get_contents((string) $dump->path))->toContain('Bea Two')
        ->and((string) file_get_contents((string) $dump->path))->toContain('ch-psc:notifBea2');
});

it('walks two psc parts as two dumps and never keeps both raw files', function (): void {
    fakeCompaniesHousePscParts();

    $dump = resolve(IngestCompaniesHouseDump::class)->handle('ch_psc', 2);

    $dumps = Dump::query()->get();

    expect($dumps)->toHaveCount(2)
        ->and($dump->dataset)->toBe('ch_psc')
        ->and($dump->status)->toBe(DumpStatus::Completed)
        ->and($dumps->every(fn (Dump $item): bool => $item->status === DumpStatus::Completed && $item->path === null))->toBeTrue();

    foreach ($dumps as $item) {
        expect(is_file(storage_path('app/dumps/'.$item->id.'.zip')))->toBeFalse()
            ->and(is_file(storage_path('app/dumps/'.$item->id.'.jsonl')))->toBeFalse();
    }

    $graph = app(GraphClient::class);

    expect(collect($graph->search('Ada Example', 5))->contains(
        fn (array $hit): bool => $hit['label'] === GraphNodeLabel::Person->value,
    ))->toBeTrue()
        ->and(collect($graph->search('Bea Two', 5))->isNotEmpty())->toBeTrue();
});
