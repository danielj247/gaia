<?php

declare(strict_types=1);

use App\Actions\AssertSameAs;
use App\Enums\GraphEdgeType;
use App\Enums\GraphNodeLabel;
use App\Enums\IdentifierKind;
use App\Graph\GraphClient;

it('links two people without collapsing identifiers', function (): void {
    $graph = app(GraphClient::class);
    $graph->mergeNode(GraphNodeLabel::Person, 'p-left', [
        'caption' => 'Ada Example',
        'sourceId' => 'ofac-left',
        'aliases' => 'Ada AKA',
    ]);
    $graph->mergeNode(GraphNodeLabel::Person, 'p-right', [
        'caption' => 'Ada Example',
        'sourceId' => 'ofac-right',
    ]);
    $graph->mergeNode(GraphNodeLabel::Identifier, 'id:left', [
        'kind' => IdentifierKind::OpenSanctionsId->value,
        'value' => 'ofac-left',
        'caption' => 'ofac-left',
    ]);
    $graph->mergeNode(GraphNodeLabel::Identifier, 'id:right', [
        'kind' => IdentifierKind::OpenSanctionsId->value,
        'value' => 'ofac-right',
        'caption' => 'ofac-right',
    ]);
    $graph->mergeEdge(GraphEdgeType::HasIdentifier, GraphNodeLabel::Person, 'p-left', GraphNodeLabel::Identifier, 'id:left');
    $graph->mergeEdge(GraphEdgeType::HasIdentifier, GraphNodeLabel::Person, 'p-right', GraphNodeLabel::Identifier, 'id:right');

    resolve(AssertSameAs::class)->handle('p-left', 'ofac-right', 'analyst@example.test');

    $ids = collect($graph->neighborhood('p-left', 1, 20)->nodes)->pluck('id');
    $types = collect($graph->neighborhood('p-left', 1, 20)->edges)->pluck('type');

    expect($ids)->toContain('p-left')
        ->and($ids)->toContain('p-right')
        ->and($ids)->toContain('id:left')
        ->and($ids)->not->toContain('id:right')
        ->and($types)->toContain(GraphEdgeType::SameAs->value)
        ->and($graph->search('aka', 10)[0]['id'])->toBe('p-left')
        ->and($graph->search('ofac-right', 10)[0]['id'])->toBe('p-right');
});

it('rejects missing or identical people', function (): void {
    $graph = app(GraphClient::class);
    $graph->mergeNode(GraphNodeLabel::Person, 'p-only', ['caption' => 'Ada Example']);

    expect(fn () => resolve(AssertSameAs::class)->handle('p-only', 'p-missing', 'analyst'))
        ->toThrow(InvalidArgumentException::class)
        ->and(fn () => resolve(AssertSameAs::class)->handle('p-only', 'p-only', 'analyst'))
        ->toThrow(InvalidArgumentException::class);
});
