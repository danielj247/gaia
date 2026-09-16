<?php

declare(strict_types=1);

use App\Enums\GraphEdgeType;
use App\Enums\GraphNodeLabel;
use App\Graph\InMemoryGraphClient;

it('merges nodes and edges and searches captions', function (): void {
    $graph = new InMemoryGraphClient();
    $graph->ensureSchema();
    $graph->mergeNode(GraphNodeLabel::Person, 'p1', ['caption' => 'Ada Example', 'name' => 'Ada Example']);
    $graph->mergeNode(GraphNodeLabel::Organization, 'o1', ['caption' => 'Example Holdings']);
    $graph->mergeEdge(GraphEdgeType::Owns, GraphNodeLabel::Person, 'p1', GraphNodeLabel::Organization, 'o1');

    expect($graph->search('ada', 10))->toHaveCount(1)
        ->and($graph->search('', 10))->toBeEmpty()
        ->and($graph->findPersonId('p1'))->toBe('p1')
        ->and($graph->findPersonId('o1'))->toBeNull()
        ->and($graph->stats())->toBe(['nodes' => 2, 'edges' => 1]);

    $neighborhood = $graph->neighborhood('p1', 1, 10);

    expect($neighborhood->nodes)->toHaveCount(2)
        ->and($neighborhood->edges)->toHaveCount(1)
        ->and($neighborhood->truncated)->toBeFalse();
});

it('merges a mapped graph exactly like the equivalent single merges', function (): void {
    $mapped = [
        'nodes' => [
            ['label' => GraphNodeLabel::Person, 'id' => 'p1', 'properties' => ['caption' => 'Ada']],
            ['label' => GraphNodeLabel::Organization, 'id' => 'o1', 'properties' => ['caption' => 'Holdings']],
            ['label' => GraphNodeLabel::Organization, 'id' => 'o1', 'properties' => ['status' => 'active']],
        ],
        'edges' => [
            ['type' => GraphEdgeType::Owns, 'fromLabel' => GraphNodeLabel::Person, 'fromId' => 'p1', 'toLabel' => GraphNodeLabel::Organization, 'toId' => 'o1', 'properties' => ['share' => '50%']],
            ['type' => GraphEdgeType::RelatedTo, 'fromLabel' => GraphNodeLabel::Other, 'fromId' => 'p1', 'toLabel' => GraphNodeLabel::Other, 'toId' => 'missing', 'properties' => []],
        ],
    ];

    $batched = new InMemoryGraphClient();
    $batched->mergeGraph($mapped);

    $single = new InMemoryGraphClient();

    foreach ($mapped['nodes'] as $node) {
        $single->mergeNode($node['label'], $node['id'], $node['properties']);
    }

    foreach ($mapped['edges'] as $edge) {
        $single->mergeEdge($edge['type'], $edge['fromLabel'], $edge['fromId'], $edge['toLabel'], $edge['toId'], $edge['properties']);
    }

    expect($batched->stats())->toBe(['nodes' => 3, 'edges' => 2])
        ->and($batched->neighborhood('p1', 2, 10))->toEqual($single->neighborhood('p1', 2, 10))
        ->and(collect($batched->neighborhood('o1', 1, 10)->nodes)->firstWhere('id', 'o1')['properties'] ?? null)
        ->toBe(['caption' => 'Holdings', 'id' => 'o1', 'status' => 'active']);
});

it('skips edges when an endpoint is missing', function (): void {
    $graph = new InMemoryGraphClient();
    $graph->mergeNode(GraphNodeLabel::Person, 'p1', ['caption' => 'Ada']);
    $graph->mergeEdge(GraphEdgeType::Owns, GraphNodeLabel::Person, 'p1', GraphNodeLabel::Organization, 'missing');
    $graph->mergeEdge(GraphEdgeType::Owns, GraphNodeLabel::Organization, 'missing', GraphNodeLabel::Person, 'p1');

    expect($graph->stats()['edges'])->toBe(0);
});

it('truncates neighborhoods and falls back to captions', function (): void {
    $graph = new InMemoryGraphClient();
    $graph->mergeNode(GraphNodeLabel::Person, 'p1', []);
    $graph->mergeNode(GraphNodeLabel::Person, 'p2', ['name' => 'Bea']);
    $graph->mergeNode(GraphNodeLabel::Person, 'p3', ['value' => 'Cara']);
    $graph->mergeEdge(GraphEdgeType::RelatedTo, GraphNodeLabel::Person, 'p1', GraphNodeLabel::Person, 'p2');
    $graph->mergeEdge(GraphEdgeType::RelatedTo, GraphNodeLabel::Person, 'p2', GraphNodeLabel::Person, 'p3');
    $graph->mergeEdge(GraphEdgeType::RelatedTo, GraphNodeLabel::Other, 'missing', GraphNodeLabel::Person, 'p1');

    $neighborhood = $graph->neighborhood('p1', 2, 1);

    expect($neighborhood->truncated)->toBeTrue()
        ->and($neighborhood->nodes)->toHaveCount(1)
        ->and($graph->search('bea', 1))->toHaveCount(1)
        ->and($graph->search('nobody', 10))->toBeEmpty();
});

it('finds interval endpoints by id when the label is other', function (): void {
    $graph = new InMemoryGraphClient();
    $graph->mergeNode(GraphNodeLabel::Person, 'p1', ['caption' => 'Ada']);
    $graph->mergeNode(GraphNodeLabel::Organization, 'o1', ['caption' => 'Holdings']);
    $graph->mergeEdge(GraphEdgeType::Owns, GraphNodeLabel::Other, 'p1', GraphNodeLabel::Other, 'o1');

    expect($graph->stats()['edges'])->toBe(1);
});

it('matches aliases and does not expand dump hubs', function (): void {
    $graph = new InMemoryGraphClient();
    $graph->mergeNode(GraphNodeLabel::Person, 'p1', [
        'caption' => 'Ada Example',
        'aliases' => 'Ada AKA',
        'sourceId' => 'ofac-100',
    ]);
    $graph->mergeNode(GraphNodeLabel::Organization, 'o1', ['caption' => 'Example Holdings']);
    $graph->mergeNode(GraphNodeLabel::Dump, 'd1', ['caption' => 'us_ofac_sdn']);
    $graph->mergeNode(GraphNodeLabel::Country, 'c1', ['caption' => 'Exampleland']);
    $graph->mergeNode(GraphNodeLabel::Sanction, 's1', ['caption' => 'OFAC-SDN']);
    $graph->mergeNode(GraphNodeLabel::Person, 'p2', ['caption' => 'Other Person']);
    $graph->mergeEdge(GraphEdgeType::Owns, GraphNodeLabel::Person, 'p1', GraphNodeLabel::Organization, 'o1');
    $graph->mergeEdge(GraphEdgeType::AppearsInDump, GraphNodeLabel::Person, 'p1', GraphNodeLabel::Dump, 'd1');
    $graph->mergeEdge(GraphEdgeType::AppearsInDump, GraphNodeLabel::Person, 'p2', GraphNodeLabel::Dump, 'd1');
    $graph->mergeEdge(GraphEdgeType::CitizenOf, GraphNodeLabel::Person, 'p1', GraphNodeLabel::Country, 'c1');
    $graph->mergeEdge(GraphEdgeType::CitizenOf, GraphNodeLabel::Person, 'p2', GraphNodeLabel::Country, 'c1');
    $graph->mergeEdge(GraphEdgeType::SanctionedUnder, GraphNodeLabel::Person, 'p1', GraphNodeLabel::Sanction, 's1');
    $graph->mergeEdge(GraphEdgeType::SanctionedUnder, GraphNodeLabel::Person, 'p2', GraphNodeLabel::Sanction, 's1');

    $neighborhood = $graph->neighborhood('p1', 2, 20);
    $ids = collect($neighborhood->nodes)->pluck('id');

    expect($graph->search('aka', 10))->toHaveCount(1)
        ->and($graph->search('aka', 10)[0]['id'])->toBe('p1')
        ->and($graph->search('ofac-100', 10)[0]['id'])->toBe('p1')
        ->and($graph->findPersonId('ofac-100'))->toBe('p1')
        ->and($graph->findPersonId(''))->toBeNull()
        ->and($graph->search('us_ofac', 10))->toBeEmpty()
        ->and($ids)->toContain('p1')
        ->and($ids)->toContain('o1')
        ->and($ids)->not->toContain('d1')
        ->and($ids)->not->toContain('c1')
        ->and($ids)->not->toContain('s1')
        ->and($ids)->not->toContain('p2');

    $hubIds = collect($graph->neighborhood('c1', 2, 20)->nodes)->pluck('id');

    expect($hubIds)->toContain('c1')
        ->and($hubIds)->not->toContain('p1')
        ->and($hubIds)->not->toContain('p2');
});

it('creates other placeholders for interval endpoints and dedupes application ids', function (): void {
    $graph = new InMemoryGraphClient();
    $graph->mergeNode(GraphNodeLabel::Person, 'shared', ['caption' => 'Ada']);
    $graph->mergeNode(GraphNodeLabel::Organization, 'shared', ['caption' => 'Ada Co']);
    $graph->mergeEdge(GraphEdgeType::Owns, GraphNodeLabel::Other, 'shared', GraphNodeLabel::Other, 'missing-org');

    $ids = collect($graph->neighborhood('shared', 1, 10)->nodes)->pluck('id');

    expect($graph->stats()['edges'])->toBe(1)
        ->and($ids->count())->toBe($ids->unique()->count())
        ->and($ids)->toContain('shared')
        ->and($ids)->toContain('missing-org');
});
