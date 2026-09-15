<?php

declare(strict_types=1);

use App\Actions\FetchOfficialList;
use App\Actions\MapOfficialRecord;
use App\Actions\ReleaseDumpFile;
use App\Enums\DumpStatus;
use App\Enums\GraphNodeLabel;
use App\Models\Dump;
use Illuminate\Support\Facades\Http;

it('routes official datasets and returns an empty graph for an unknown list', function (): void {
    $mapper = resolve(MapOfficialRecord::class);

    $uk = $mapper->handle('uksl', ['Unique ID' => 'GBR001', 'Designation Type' => 'Individual', 'Name 6' => 'Ada'], 'dump-1');
    $unknown = $mapper->handle('crime', ['id' => 'x'], 'dump-1');

    $company = $mapper->handle('ch_companies', ['CompanyNumber' => '00000006', 'CompanyName' => 'EXAMPLE ONE LTD'], 'dump-1');
    $psc = $mapper->handle('ch_psc', companiesHousePscRecord(0), 'dump-1');

    expect(collect($uk['nodes'])->contains(fn (array $node): bool => $node['label'] === GraphNodeLabel::Person))->toBeTrue()
        ->and($unknown)->toBe(['nodes' => [], 'edges' => []])
        ->and(collect($company['nodes'])->contains(fn (array $node): bool => $node['label'] === GraphNodeLabel::Organization
            && $node['id'] === '00000006'))->toBeTrue()
        ->and(collect($company['nodes'])->contains(fn (array $node): bool => $node['label'] === GraphNodeLabel::Person))->toBeFalse()
        ->and(collect($psc['nodes'])->contains(fn (array $node): bool => $node['label'] === GraphNodeLabel::Person))->toBeTrue();
});

it('ignores non-string official allow-list entries', function (): void {
    config(['graph.official.lists' => ['uksl', '', 2]]);

    Http::fake([
        'https://sanctionslist.fcdo.gov.uk/*' => Http::response(
            file_get_contents(base_path('tests/Fixtures/official/uksl.sample.csv')),
            200,
        ),
    ]);

    $dump = resolve(FetchOfficialList::class)->handle('uksl', 1);

    expect($dump->dataset)->toBe('uksl')
        ->and($dump->status)->toBe(DumpStatus::Pending);
});

it('rejects a blank official list or a missing allow-list', function (): void {
    expect(fn () => resolve(FetchOfficialList::class)->handle('   ', 1))
        ->toThrow(RuntimeException::class);

    config(['graph.official.lists' => null]);

    expect(fn () => resolve(FetchOfficialList::class)->handle('uksl', 1))
        ->toThrow(RuntimeException::class);
});

it('rejects an official list that is allow-listed but not implemented', function (): void {
    config(['graph.official.lists' => ['uksl', 'ofac_sdn', 'eu_fsf', 'nope']]);

    expect(fn () => resolve(FetchOfficialList::class)->handle('nope', 1))
        ->toThrow(RuntimeException::class);
});

it('leaves fixture dump paths in place and clears stored downloads', function (): void {
    $fixture = Dump::factory()->create([
        'path' => base_path('tests/Fixtures/opensanctions/us_ofac_sdn.sample.jsonl'),
        'status' => DumpStatus::Completed,
    ]);

    $kept = resolve(ReleaseDumpFile::class)->handle($fixture);

    expect($kept->path)->toBe(base_path('tests/Fixtures/opensanctions/us_ofac_sdn.sample.jsonl'))
        ->and(is_file((string) $kept->path))->toBeTrue();

    $directory = storage_path('app/dumps');

    if (! is_dir($directory)) {
        mkdir($directory, 0755, true);
    }

    $path = $directory.'/release-me.jsonl';
    file_put_contents($path, "{}\n");

    $stored = Dump::factory()->create([
        'path' => $path,
        'status' => DumpStatus::Completed,
    ]);

    $released = resolve(ReleaseDumpFile::class)->handle($stored);

    expect($released->path)->toBeNull()
        ->and(is_file($path))->toBeFalse();

    $empty = resolve(ReleaseDumpFile::class)->handle(Dump::factory()->create([
        'path' => null,
        'status' => DumpStatus::Completed,
    ]));

    expect($empty->path)->toBeNull();
});
