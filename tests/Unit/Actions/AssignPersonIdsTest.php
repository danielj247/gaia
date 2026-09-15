<?php

declare(strict_types=1);

use App\Actions\AssignPersonIds;
use App\Enums\GraphEdgeType;
use App\Enums\GraphNodeLabel;
use App\Graph\FollowTheMoneyMapper;
use App\Models\PersonKey;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

it('gives people a gaia uuid and keeps hash-keyed identifiers', function (): void {
    $mapper = new FollowTheMoneyMapper();
    $mapped = $mapper->map([
        'id' => 'ofac-100',
        'caption' => 'Ada Example',
        'schema' => 'Person',
        'properties' => [
            'name' => ['Ada Example'],
            'email' => ['ada@example.test'],
        ],
    ], 'dump-1');

    $assigned = resolve(AssignPersonIds::class)->handle($mapped);
    $person = collect($assigned['nodes'])->firstWhere('label', GraphNodeLabel::Person);
    $identifier = collect($assigned['nodes'])->first(
        fn (array $node): bool => $node['label'] === GraphNodeLabel::Identifier
            && ($node['properties']['kind'] ?? null) === 'email',
    );

    expect($person)->not->toBeNull()
        ->and(Str::isUuid($person['id']))->toBeTrue()
        ->and($person['properties']['sourceId'])->toBe('ofac-100')
        ->and($identifier['id'] ?? null)->toStartWith('id:')
        ->and(collect($assigned['edges'])->firstWhere('type', GraphEdgeType::HasIdentifier)['fromId'] ?? null)->toBe($person['id']);
});

it('remaps interval endpoints that already have person keys', function (): void {
    PersonKey::factory()->create([
        'source_id' => 'ofac-100',
        'gaia_id' => '11111111-1111-4111-8111-111111111111',
    ]);

    $mapped = (new FollowTheMoneyMapper())->map([
        'id' => 'rel-1',
        'schema' => 'Ownership',
        'properties' => [
            'owner' => ['ofac-100'],
            'asset' => ['ofac-200'],
        ],
    ], 'dump-1');

    $assigned = resolve(AssignPersonIds::class)->handle($mapped);

    expect($assigned['edges'][0]['fromId'])->toBe('11111111-1111-4111-8111-111111111111')
        ->and($assigned['edges'][0]['toId'])->toBe('ofac-200');
});

it('remaps interval endpoints from an existing graph person', function (): void {
    app(App\Graph\GraphClient::class)->mergeNode(GraphNodeLabel::Person, 'gaia-graph', [
        'caption' => 'Ada Example',
        'sourceId' => 'ofac-graph',
    ]);

    $mapped = (new FollowTheMoneyMapper())->map([
        'id' => 'rel-2',
        'schema' => 'Ownership',
        'properties' => [
            'owner' => ['ofac-graph'],
            'asset' => ['ofac-200'],
        ],
    ], 'dump-1');

    $assigned = resolve(AssignPersonIds::class)->handle($mapped);

    expect($assigned['edges'][0]['fromId'])->toBe('gaia-graph');
});

it('does not look up hub endpoints that can never be people', function (): void {
    $mapped = (new FollowTheMoneyMapper())->map([
        'id' => 'ofac-101',
        'schema' => 'Person',
        'caption' => 'Ada Example',
        'properties' => [
            'name' => ['Ada Example'],
            'nationality' => ['gb'],
            'email' => ['ada@example.test'],
        ],
    ], 'dump-1');

    $looked = [];

    DB::listen(function (QueryExecuted $query) use (&$looked): void {
        if (! str_contains($query->sql, 'person_keys')) {
            return;
        }

        foreach ($query->bindings as $binding) {
            if (is_string($binding)) {
                $looked[] = $binding;
            }
        }
    });

    $assigned = resolve(AssignPersonIds::class)->handle($mapped);
    $hubIds = collect($assigned['edges'])
        ->reject(fn (array $edge): bool => $edge['toLabel'] === GraphNodeLabel::Person)
        ->pluck('toId')
        ->all();

    expect($hubIds)->toContain('dump-1', 'country:gb')
        ->and($looked)->toContain('ofac-101')
        ->and(array_intersect($looked, $hubIds))->toBeEmpty();
});

it('falls back to the node id when sourceId is missing', function (): void {
    $assigned = resolve(AssignPersonIds::class)->handle([
        'nodes' => [[
            'label' => GraphNodeLabel::Person,
            'id' => 'ofac-naked',
            'properties' => [
                'caption' => 'Ada Example',
            ],
        ]],
        'edges' => [],
    ]);

    expect(Str::isUuid($assigned['nodes'][0]['id']))->toBeTrue()
        ->and($assigned['nodes'][0]['properties']['sourceId'])->toBe('ofac-naked');
});
