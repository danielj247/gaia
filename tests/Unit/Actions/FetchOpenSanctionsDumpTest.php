<?php

declare(strict_types=1);

use App\Actions\FetchOpenSanctionsDump;
use App\Enums\DumpStatus;
use Illuminate\Support\Facades\Http;

it('downloads a public dataset and stores json lines', function (): void {
    Http::fake([
        'https://data.opensanctions.org/*' => Http::response(
            json_encode([
                ['id' => 'ofac-1', 'schema' => 'Person', 'caption' => 'Ada'],
            ], JSON_THROW_ON_ERROR),
            200,
        ),
    ]);

    $dump = resolve(FetchOpenSanctionsDump::class)->handle('us_ofac_sdn', 10);

    expect($dump->source)->toBe('opensanctions')
        ->and($dump->dataset)->toBe('us_ofac_sdn')
        ->and($dump->status)->toBe(DumpStatus::Pending)
        ->and(is_file((string) $dump->path))->toBeTrue()
        ->and(file_get_contents((string) $dump->path))->toContain('ofac-1');
});

it('rejects an invalid dataset id', function (): void {
    expect(fn () => resolve(FetchOpenSanctionsDump::class)->handle('../etc/passwd', 10))
        ->toThrow(RuntimeException::class);
});

it('fails when the download is not successful', function (): void {
    Http::fake([
        'https://data.opensanctions.org/*' => Http::response('nope', 404),
    ]);

    expect(fn () => resolve(FetchOpenSanctionsDump::class)->handle('us_ofac_sdn', 10))
        ->toThrow(RuntimeException::class);
});

it('rejects a blank dataset id', function (): void {
    expect(fn () => resolve(FetchOpenSanctionsDump::class)->handle('   ', 10))
        ->toThrow(RuntimeException::class);
});

it('keeps already-normalized json lines', function (): void {
    Http::fake([
        'https://data.opensanctions.org/*' => Http::response("{\"id\":\"ofac-1\"}\n", 200),
    ]);

    $dump = resolve(FetchOpenSanctionsDump::class)->handle('us_ofac_sdn', 1);

    expect(file_get_contents((string) $dump->path))->toBe("{\"id\":\"ofac-1\"}\n");
});

it('skips non-object rows when normalizing a json array', function (): void {
    Http::fake([
        'https://data.opensanctions.org/*' => Http::response(
            json_encode(['skip-me', ['id' => 'ofac-2', 'schema' => 'Person']], JSON_THROW_ON_ERROR),
            200,
        ),
    ]);

    $dump = resolve(FetchOpenSanctionsDump::class)->handle('us_ofac_sdn', 1);

    expect(file_get_contents((string) $dump->path))->toContain('ofac-2')
        ->and(file_get_contents((string) $dump->path))->not->toContain('skip-me');
});

it('accepts an empty download', function (): void {
    Http::fake([
        'https://data.opensanctions.org/*' => Http::response('', 200),
    ]);

    $dump = resolve(FetchOpenSanctionsDump::class)->handle('us_ofac_sdn', 1);

    expect($dump->status)->toBe(DumpStatus::Pending)
        ->and(is_file((string) $dump->path))->toBeTrue();
});

it('leaves an invalid json array untouched', function (): void {
    Http::fake([
        'https://data.opensanctions.org/*' => Http::response('[not-json', 200),
    ]);

    $dump = resolve(FetchOpenSanctionsDump::class)->handle('us_ofac_sdn', 1);

    expect(file_get_contents((string) $dump->path))->toBe('[not-json');
});
