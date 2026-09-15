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
                fn (array $statement): bool => str_contains($statement['statement'], 'ON CREATE SET a:Other'),
            )['statement'],
        )->toContain('MERGE (a {id: $fromId})')
        ->and(
            collect($session->statements())->first(
                fn (array $statement): bool => str_contains($statement['statement'], 'MERGE (a)-[r:OWNS]'),
            )['parameters']['props'],
        )->toBeInstanceOf(CypherMap::class)
        ->and(
            collect($session->statements())->first(
                fn (array $statement): bool => str_contains($statement['statement'], 'truncated'),
            )['statement'],
        )->toContain('WITH raw[..$limit] AS nodes, size(raw) > $limit AS truncated')
        ->and(
            collect($session->statements())->first(
                fn (array $statement): bool => str_contains($statement['statement'], 'truncated'),
            )['statement'],
        )->toContain('WITH [start] + neighbors AS raw')
        ->and(
            collect($session->statements())->first(
                fn (array $statement): bool => str_contains($statement['statement'], 'truncated'),
            )['statement'],
        )->not->toContain('collect(DISTINCT start) +')
        ->and(
            collect($session->statements())->first(
                fn (array $statement): bool => str_contains($statement['statement'], 'truncated'),
            )['statement'],
        )->toContain('NOT (start:Dump OR start:Country OR start:Sanction)')
        ->and(
            collect($session->statements())->first(
                fn (array $statement): bool => str_contains($statement['statement'], 'OPTIONAL MATCH path'),
            )['statement'],
        )->toContain('NOT (n:Dump OR n:Country OR n:Sanction)');
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
        ->and($client->stats()['nodes'])->toBe(3)
        ->and(
            collect($session->statements())->first(
                fn (array $statement): bool => str_contains($statement['statement'], 'CONTAINS'),
            )['statement'],
        )->toContain('n.aliases')
        ->and(
            collect($session->statements())->first(
                fn (array $statement): bool => str_contains($statement['statement'], 'CONTAINS'),
            )['statement'],
        )->toContain('n.sourceId')
        ->and(
            collect($session->statements())->first(
                fn (array $statement): bool => str_contains($statement['statement'], 'CONTAINS'),
            )['statement'],
        )->toContain('n:Dump OR n:Country OR n:Sanction');
});

it('finds people by source id or application id', function (): void {
    $client = new Neo4jGraphClient(new RecordingGraphSession([
        ['id' => 'p1'],
    ]));

    expect($client->findPersonId(''))->toBeNull()
        ->and($client->findPersonId('ofac-100'))->toBe('p1');
});

it('indexes person source ids so lookups seek instead of scanning the label', function (): void {
    $session = new RecordingGraphSession([]);

    new Neo4jGraphClient($session)->ensureSchema();

    expect(
        collect($session->statements())->first(
            fn (array $statement): bool => str_contains($statement['statement'], 'CREATE INDEX'),
        )['statement'],
    )->toBe('CREATE INDEX person_source_id IF NOT EXISTS FOR (n:Person) ON (n.sourceId)');
});

it('normalizes flat nodes and relationship payloads', function (): void {
    $session = new RecordingGraphSession([
        [
            'a' => ['id' => 'p1', 'caption' => 12, 0 => 'skip'],
            'b' => ['id' => 'p2', 'labels' => ['Person']],
            'r' => ['type' => 'RELATED_TO', 'start' => '1', 'end' => '2', 'properties' => ['since' => '2020', 1 => 'skip']],
            'truncated' => 'true',
            'count' => 'not-a-number',
        ],
    ]);
    $client = new Neo4jGraphClient($session);

    $neighborhood = $client->neighborhood('p1', 9, 0);

    expect($neighborhood->truncated)->toBeTrue()
        ->and($neighborhood->nodes)->toHaveCount(2)
        ->and($neighborhood->nodes[0]['caption'])->toBe('p1')
        ->and($neighborhood->edges)->toHaveCount(1)
        ->and($neighborhood->edges[0]['source'])->toBe('p1')
        ->and($neighborhood->edges[0]['target'])->toBe('p2')
        ->and($client->search('ada', 10))->toBeEmpty()
        ->and($client->stats()['nodes'])->toBe(0);
});

it('drops edges that only have neo4j internal identities', function (): void {
    $session = new RecordingGraphSession([
        [
            'a' => ['labels' => ['Person']],
            'b' => ['labels' => ['Organization']],
            'r' => ['type' => 'OWNS', 'start' => '1', 'end' => '3'],
            'truncated' => false,
        ],
    ]);

    $neighborhood = new Neo4jGraphClient($session)->neighborhood('p1', 1, 10);

    expect($neighborhood->edges)->toBeEmpty()
        ->and($neighborhood->nodes)->toBeEmpty();
});
