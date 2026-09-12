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
        ->and($graph->stats())->toBe(['nodes' => 2, 'edges' => 1]);

    $neighborhood = $graph->neighborhood('p1', 1, 10);

    expect($neighborhood->nodes)->toHaveCount(2)
        ->and($neighborhood->edges)->toHaveCount(1)
        ->and($neighborhood->truncated)->toBeFalse();
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
