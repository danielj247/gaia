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

it('merges a mapped graph with one unwind statement per node label and edge group', function (): void {
    $session = new RecordingGraphSession([]);
    $client = new Neo4jGraphClient($session);

    $client->mergeGraph([
        'nodes' => [
            ['label' => GraphNodeLabel::Organization, 'id' => 'o2', 'properties' => ['caption' => 'Two Ltd']],
            ['label' => GraphNodeLabel::Organization, 'id' => 'o1', 'properties' => ['caption' => 'One Ltd']],
            ['label' => GraphNodeLabel::Dump, 'id' => 'd1', 'properties' => []],
            ['label' => GraphNodeLabel::Country, 'id' => 'country:gb', 'properties' => ['caption' => 'GB']],
            ['label' => GraphNodeLabel::Country, 'id' => 'country:gb', 'properties' => ['code' => 'GB']],
        ],
        'edges' => [
            ['type' => GraphEdgeType::AppearsInDump, 'fromLabel' => GraphNodeLabel::Organization, 'fromId' => 'o1', 'toLabel' => GraphNodeLabel::Dump, 'toId' => 'd1', 'properties' => ['list' => 'ch_companies']],
            ['type' => GraphEdgeType::AppearsInDump, 'fromLabel' => GraphNodeLabel::Organization, 'fromId' => 'o2', 'toLabel' => GraphNodeLabel::Dump, 'toId' => 'd1', 'properties' => []],
            ['type' => GraphEdgeType::LocatedAt, 'fromLabel' => GraphNodeLabel::Organization, 'fromId' => 'o1', 'toLabel' => GraphNodeLabel::Country, 'toId' => 'country:gb', 'properties' => ['field' => 'country']],
            ['type' => GraphEdgeType::RelatedTo, 'fromLabel' => GraphNodeLabel::Other, 'fromId' => 'o1', 'toLabel' => GraphNodeLabel::Other, 'toId' => 'x1', 'properties' => []],
        ],
    ]);

    $statements = collect($session->statements());
    $organizations = $statements->first(
        fn (array $statement): bool => str_contains($statement['statement'], 'MERGE (n:Organization {id: row.id})'),
    );
    $countries = $statements->first(
        fn (array $statement): bool => str_contains($statement['statement'], 'MERGE (n:Country {id: row.id})'),
    );
    $appears = $statements->first(
        fn (array $statement): bool => str_contains($statement['statement'], 'MERGE (a)-[r:APPEARS_IN_DUMP]->(b)'),
    );
    $located = $statements->first(
        fn (array $statement): bool => str_contains($statement['statement'], 'MERGE (a)-[r:LOCATED_AT]->(b)'),
    );
    $other = $statements->first(
        fn (array $statement): bool => str_contains($statement['statement'], 'MERGE (a)-[r:RELATED_TO]->(b)'),
    );

    expect($statements)->toHaveCount(6)
        ->and($organizations['statement'])->toStartWith('UNWIND $rows AS row')
        ->and($organizations['statement'])->toContain('SET n += row.props')
        ->and($organizations['parameters']['rows'])->toHaveCount(2)
        ->and($organizations['parameters']['rows'][0]['id'])->toBe('o1')
        ->and($organizations['parameters']['rows'][1]['id'])->toBe('o2')
        ->and($organizations['parameters']['rows'][0]['props'])->toBeInstanceOf(CypherMap::class)
        ->and($organizations['parameters']['rows'][0]['props']->toArray())->toBe(['caption' => 'One Ltd', 'id' => 'o1'])
        ->and($countries['parameters']['rows'])->toHaveCount(1)
        ->and($countries['parameters']['rows'][0]['props']->toArray())->toEqual(['caption' => 'GB', 'code' => 'GB', 'id' => 'country:gb'])
        ->and($appears['statement'])->toStartWith('UNWIND $rows AS row')
        ->and($appears['statement'])->toContain('MATCH (a:Organization {id: row.fromId}), (b:Dump {id: row.toId})')
        ->and($appears['statement'])->toContain('SET r += row.props')
        ->and($appears['parameters']['rows'])->toHaveCount(2)
        ->and($appears['parameters']['rows'][1]['props'])->toBeInstanceOf(CypherMap::class)
        ->and($located['statement'])->toContain('MATCH (a:Organization {id: row.fromId}), (b:Country {id: row.toId})')
        ->and($other['statement'])->toContain('MERGE (a {id: row.fromId})')
        ->and($other['statement'])->toContain('ON CREATE SET a:Other, a.id = row.fromId, a.caption = row.fromId')
        ->and($other['statement'])->toContain('MERGE (b {id: row.toId})')
        ->and($statements->search(fn (array $statement): bool => $statement === $appears))
        ->toBeGreaterThan($statements->search(fn (array $statement): bool => $statement === $countries));
});

it('keeps numeric company numbers as string ids in batched rows', function (): void {
    $session = new RecordingGraphSession([]);

    new Neo4jGraphClient($session)->mergeGraph([
        'nodes' => [
            ['label' => GraphNodeLabel::Organization, 'id' => '12345678', 'properties' => ['caption' => 'Three Ltd']],
            ['label' => GraphNodeLabel::Organization, 'id' => '6', 'properties' => ['caption' => 'One Ltd']],
        ],
        'edges' => [
            ['type' => GraphEdgeType::AppearsInDump, 'fromLabel' => GraphNodeLabel::Organization, 'fromId' => '12345678', 'toLabel' => GraphNodeLabel::Dump, 'toId' => '01', 'properties' => []],
        ],
    ]);

    $rows = collect($session->statements())->pluck('parameters.rows');

    expect($rows[0][0]['id'])->toBe('12345678')
        ->and($rows[0][0]['props']->toArray()['id'])->toBe('12345678')
        ->and($rows[0][1]['id'])->toBe('6')
        ->and($rows[1][0]['fromId'])->toBe('12345678')
        ->and($rows[1][0]['toId'])->toBe('01');
});

it('runs nothing for an empty mapped graph', function (): void {
    $session = new RecordingGraphSession([]);

    new Neo4jGraphClient($session)->mergeGraph(['nodes' => [], 'edges' => []]);

    expect($session->statements())->toBeEmpty();
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
