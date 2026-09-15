<?php

declare(strict_types=1);

use App\Actions\FetchUkSanctionsList;
use App\Enums\DumpStatus;
use App\Models\Dump;
use Illuminate\Support\Facades\Http;

it('downloads the uk sanctions list csv into an official dump', function (): void {
    Http::fake([
        'https://sanctionslist.fcdo.gov.uk/*' => Http::response(
            file_get_contents(base_path('tests/Fixtures/official/uksl.sample.csv')),
            200,
        ),
    ]);

    $dump = resolve(FetchUkSanctionsList::class)->handle(400);

    expect($dump->source)->toBe('official')
        ->and($dump->dataset)->toBe('uksl')
        ->and($dump->status)->toBe(DumpStatus::Pending)
        ->and($dump->record_limit)->toBe(400)
        ->and($dump->attribution)->toContain('FCDO')
        ->and($dump->attribution)->toContain('UK Sanctions List')
        ->and($dump->attribution)->not->toContain('OpenSanctions')
        ->and(is_file((string) $dump->path))->toBeTrue();

    $contents = (string) file_get_contents((string) $dump->path);

    expect($contents)->toContain('"list":"uksl"')
        ->and($contents)->toContain('GBR001')
        ->and($contents)->toContain('Ada Example');
});

it('fails when the uksl csv has no unique id header', function (): void {
    Http::fake([
        'https://sanctionslist.fcdo.gov.uk/*' => Http::response("Report Date: 11-Sep-2026\nfoo,bar\n1,2\n", 200),
    ]);

    expect(fn () => resolve(FetchUkSanctionsList::class)->handle(10))
        ->toThrow(RuntimeException::class, 'UK Sanctions List CSV is missing the Unique ID header.');

    expect(Dump::query()->first()?->status)->toBe(DumpStatus::Failed);
});

it('marks the dump failed when the download is not successful', function (): void {
    Http::fake([
        'https://sanctionslist.fcdo.gov.uk/*' => Http::response('nope', 403),
    ]);

    expect(fn () => resolve(FetchUkSanctionsList::class)->handle(10))
        ->toThrow(RuntimeException::class);

    $dump = Dump::query()->first();

    expect($dump)->not->toBeNull()
        ->and($dump?->status)->toBe(DumpStatus::Failed)
        ->and($dump?->error)->toContain('HTTP 403');
});
