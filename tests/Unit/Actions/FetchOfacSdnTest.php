<?php

declare(strict_types=1);

use App\Actions\FetchOfacSdn;
use App\Enums\DumpStatus;
use App\Models\Dump;
use Illuminate\Support\Facades\Http;

it('downloads ofac sdn xml into an official dump', function (): void {
    Http::fake([
        'https://sanctionslistservice.ofac.treas.gov/*' => Http::response(
            file_get_contents(base_path('tests/Fixtures/official/ofac_sdn.sample.xml')),
            200,
        ),
    ]);

    $dump = resolve(FetchOfacSdn::class)->handle(400);

    expect($dump->source)->toBe('official')
        ->and($dump->dataset)->toBe('ofac_sdn')
        ->and($dump->status)->toBe(DumpStatus::Pending)
        ->and($dump->attribution)->toContain('OFAC')
        ->and($dump->attribution)->toContain('Specially Designated Nationals')
        ->and($dump->attribution)->not->toContain('OpenSanctions')
        ->and(is_file((string) $dump->path))->toBeTrue()
        ->and((string) file_get_contents((string) $dump->path))->toContain('"list":"ofac_sdn"')
        ->and((string) file_get_contents((string) $dump->path))->toContain('Ada Example');
});

it('converts an ofac file that has no default namespace', function (): void {
    Http::fake([
        'https://sanctionslistservice.ofac.treas.gov/*' => Http::response(
            file_get_contents(base_path('tests/Fixtures/official/ofac_sdn.nons.sample.xml')),
            200,
        ),
    ]);

    $dump = resolve(FetchOfacSdn::class)->handle(1);

    expect((string) file_get_contents((string) $dump->path))->toContain('No Namespace');
});

it('marks the dump failed when the ofac file is not xml', function (): void {
    Http::fake([
        'https://sanctionslistservice.ofac.treas.gov/*' => Http::response('nope', 200),
    ]);

    expect(fn () => resolve(FetchOfacSdn::class)->handle(10))
        ->toThrow(RuntimeException::class, 'Unable to read the OFAC SDN XML.');

    expect(Dump::query()->first()?->status)->toBe(DumpStatus::Failed);
});

it('marks the dump failed when the ofac download is forbidden', function (): void {
    Http::fake([
        'https://sanctionslistservice.ofac.treas.gov/*' => Http::response('nope', 403),
    ]);

    expect(fn () => resolve(FetchOfacSdn::class)->handle(10))
        ->toThrow(RuntimeException::class);

    expect(Dump::query()->first()?->status)->toBe(DumpStatus::Failed)
        ->and(Dump::query()->first()?->error)->toContain('HTTP 403');
});
