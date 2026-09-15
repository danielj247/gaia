<?php

declare(strict_types=1);

use App\Actions\FetchEuFsf;
use App\Enums\DumpStatus;
use App\Models\Dump;
use Illuminate\Support\Facades\Http;

it('downloads the eu financial sanctions file into an official dump', function (): void {
    config(['graph.official.eu_fsf.token' => 'test-token']);

    Http::fake([
        'https://webgate.ec.europa.eu/*' => Http::response(
            file_get_contents(base_path('tests/Fixtures/official/eu_fsf.sample.xml')),
            200,
        ),
    ]);

    $dump = resolve(FetchEuFsf::class)->handle(400);

    expect($dump->source)->toBe('official')
        ->and($dump->dataset)->toBe('eu_fsf')
        ->and($dump->status)->toBe(DumpStatus::Pending)
        ->and($dump->attribution)->toContain('European Commission')
        ->and($dump->attribution)->toContain('Financial Sanctions')
        ->and($dump->attribution)->not->toContain('OpenSanctions')
        ->and(is_file((string) $dump->path))->toBeTrue()
        ->and((string) file_get_contents((string) $dump->path))->toContain('"list":"eu_fsf"')
        ->and((string) file_get_contents((string) $dump->path))->toContain('Ada Example');
});

it('marks the dump failed when the eu download is forbidden', function (): void {
    config(['graph.official.eu_fsf.token' => 'test-token']);

    Http::fake([
        'https://webgate.ec.europa.eu/*' => Http::response('{"error":"forbidden"}', 403),
    ]);

    expect(fn () => resolve(FetchEuFsf::class)->handle(10))
        ->toThrow(RuntimeException::class);

    expect(Dump::query()->first()?->status)->toBe(DumpStatus::Failed);
});

it('converts an eu file that has no default namespace', function (): void {
    config(['graph.official.eu_fsf.token' => 'test-token']);

    Http::fake([
        'https://webgate.ec.europa.eu/*' => Http::response(
            file_get_contents(base_path('tests/Fixtures/official/eu_fsf.nons.sample.xml')),
            200,
        ),
    ]);

    $dump = resolve(FetchEuFsf::class)->handle(1);

    expect((string) file_get_contents((string) $dump->path))->toContain('No Namespace');
});

it('appends the eu token to a url that already has a query string', function (): void {
    config([
        'graph.official.eu_fsf.token' => 'test-token',
        'graph.official.eu_fsf.url' => 'https://webgate.ec.europa.eu/fsd/fsf/public/files/xmlFullSanctionsList_1_1/content?v=1',
    ]);

    Http::fake([
        'https://webgate.ec.europa.eu/*' => Http::response(
            file_get_contents(base_path('tests/Fixtures/official/eu_fsf.sample.xml')),
            200,
        ),
    ]);

    $dump = resolve(FetchEuFsf::class)->handle(1);

    expect($dump->status)->toBe(DumpStatus::Pending);

    Http::assertSent(fn ($request): bool => str_contains($request->url(), 'v=1') && str_contains($request->url(), 'token=test-token'));
});

it('rejects a missing eu fsf token before downloading', function (): void {
    config(['graph.official.eu_fsf.token' => '']);

    expect(fn () => resolve(FetchEuFsf::class)->handle(10))
        ->toThrow(RuntimeException::class, 'EU FSF token is not configured.');
});

it('rejects a non-string eu fsf token before downloading', function (): void {
    config(['graph.official.eu_fsf.token' => 123]);

    expect(fn () => resolve(FetchEuFsf::class)->handle(10))
        ->toThrow(RuntimeException::class, 'EU FSF token is not configured.');
});

it('marks the dump failed when the eu file is not xml', function (): void {
    config(['graph.official.eu_fsf.token' => 'test-token']);

    Http::fake([
        'https://webgate.ec.europa.eu/*' => Http::response('nope', 200),
    ]);

    expect(fn () => resolve(FetchEuFsf::class)->handle(10))
        ->toThrow(RuntimeException::class, 'Unable to read the EU FSF XML.');

    expect(Dump::query()->first()?->status)->toBe(DumpStatus::Failed);
});
