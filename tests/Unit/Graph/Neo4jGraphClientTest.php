<?php

declare(strict_types=1);

use App\Enums\GraphEdgeType;
use App\Enums\GraphNodeLabel;
use App\Graph\Neo4jGraphClient;
use App\Graph\RecordingGraphSession;
use Laudis\Neo4j\Types\CypherMap;

it('writes constraints merge statements and neighborhood queries', function (): void {
    $session = new RecordingGraphSession([
        [
            'a' => ['id' => 'p1', 'labels' => ['Person'], 'properties' => ['id' => 'p1', 'caption' => 'Ada']],
            'b' => ['id' => 'o1', 'labels' => ['Organization'], 'properties' => ['id' => 'o1', 'caption' => 'Holdings']],
            'r' => ['type' => 'OWNS', 'properties' => []],
            'source' => 'p1',
            'target' => 'o1',
            'relType' => 'OWNS',
            'truncated' => false,
        ],
    ]);
    $client = new Neo4jGraphClient($session);

    $client->ensureSchema();
    $client->mergeNode(GraphNodeLabel::Person, 'p1', ['caption' => 'Ada']);
    $client->mergeEdge(GraphEdgeType::Owns, GraphNodeLabel::Person, 'p1', GraphNodeLabel::Organization, 'o1');
    $client->mergeEdge(GraphEdgeType::RelatedTo, GraphNodeLabel::Other, 'p1', GraphNodeLabel::Other, 'o1');

    $neighborhood = $client->neighborhood('p1', 2, 50);

    expect($neighborhood->nodes)->toHaveCount(2)
        ->and($neighborhood->edges)->toHaveCount(1)
        ->and($session->statements())->not->toBeEmpty()
        ->and(
            collect($session->statements())->first(
                fn (array $statement): bool => str_contains($statement['statement'], 'MERGE (a)-[r:OWNS]'),
            )['parameters']['props'],
        )->toBeInstanceOf(CypherMap::class)
        ->and(
            collect($session->statements())->first(
                fn (array $statement): bool => str_contains($statement['statement'], 'truncated'),
            )['statement'],
        )->toContain('WITH raw[..$limit] AS nodes, size(raw) > $limit AS truncated');
});

it('searches and counts through the session', function (): void {
    $session = new RecordingGraphSession([
        [
            'n' => ['id' => 'p1', 'labels' => ['Person'], 'properties' => ['id' => 'p1', 'caption' => 'Ada']],
            'count' => 3,
        ],
    ]);
    $client = new Neo4jGraphClient($session);

    expect($client->search('', 10))->toBeEmpty()
        ->and($client->search('ada', 10))->toHaveCount(1)
        ->and($client->stats()['nodes'])->toBe(3);
});

it('normalizes flat nodes and relationship payloads', function (): void {
    $session = new RecordingGraphSession([
        [
            'a' => ['id' => 'p1', 'caption' => 12, 0 => 'skip'],
            'b' => ['labels' => ['Person']],
            'r' => ['type' => 'RELATED_TO', 'start' => 'p1', 'end' => 'p2', 'properties' => ['since' => '2020', 1 => 'skip']],
            'truncated' => true,
            'count' => 'not-a-number',
        ],
    ]);
    $client = new Neo4jGraphClient($session);

    $neighborhood = $client->neighborhood('p1', 9, 0);

    expect($neighborhood->truncated)->toBeTrue()
        ->and($neighborhood->nodes)->toHaveCount(1)
        ->and($neighborhood->nodes[0]['caption'])->toBe('p1')
        ->and($neighborhood->edges)->toHaveCount(1)
        ->and($client->search('ada', 10))->toBeEmpty()
        ->and($client->stats()['nodes'])->toBe(0);
});
