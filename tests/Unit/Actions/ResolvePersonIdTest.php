<?php

declare(strict_types=1);

use App\Actions\ResolvePersonId;
use App\Enums\GraphNodeLabel;
use App\Graph\GraphClient;
use App\Models\PersonKey;
use Illuminate\Support\Str;

it('mints a stable gaia uuid per source id', function (): void {
    $first = resolve(ResolvePersonId::class)->handle('ofac-100');
    $second = resolve(ResolvePersonId::class)->handle('ofac-100');
    $other = resolve(ResolvePersonId::class)->handle('ofac-101');

    expect(Str::isUuid($first))->toBeTrue()
        ->and($second)->toBe($first)
        ->and($other)->not->toBe($first)
        ->and(PersonKey::query()->where('source_id', 'ofac-100')->value('gaia_id'))->toBe($first);
});

it('reuses a legacy person id already in the graph', function (): void {
    $graph = app(GraphClient::class);
    $graph->mergeNode(GraphNodeLabel::Person, 'ofac-legacy', [
        'caption' => 'Ada Example',
        'sourceId' => 'ofac-legacy',
    ]);

    expect(resolve(ResolvePersonId::class)->handle('ofac-legacy'))->toBe('ofac-legacy');
});
