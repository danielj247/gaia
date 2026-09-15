<?php

declare(strict_types=1);

use App\Actions\AssignPersonIds;
use App\Enums\GraphEdgeType;
use App\Enums\GraphNodeLabel;
use App\Graph\FollowTheMoneyMapper;
use App\Models\PersonKey;
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
