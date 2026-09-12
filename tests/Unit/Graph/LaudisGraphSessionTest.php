<?php

declare(strict_types=1);

use App\Graph\LaudisGraphSession;
use Laudis\Neo4j\Contracts\ClientInterface;
use Laudis\Neo4j\Databags\SummarizedResult;
use Laudis\Neo4j\Types\CypherList;
use Laudis\Neo4j\Types\CypherMap;
use Laudis\Neo4j\Types\Node;
use Laudis\Neo4j\Types\Relationship;
use Mockery;

it('normalizes neo4j nodes relationships and nested values', function (): void {
    $summary = null;
    $node = new Node(1, new CypherList(['Person']), new CypherMap(['id' => 'p1', 'caption' => 'Ada']), 'el-1');
    $relationship = new Relationship(2, 1, 3, 'OWNS', new CypherMap(['since' => '2020']), 'rel-1', 'el-1', 'el-3');
    $record = new CypherMap([
        'n' => $node,
        'r' => $relationship,
        'list' => new CypherList(['x']),
        'map' => new CypherMap(['k' => 'v']),
        'count' => 4,
    ]);
    $result = new SummarizedResult($summary, [$record], ['n', 'r', 'list', 'map', 'count']);

    $client = Mockery::mock(ClientInterface::class);
    $client->shouldReceive('run')->once()->andReturn($result);

    $session = new LaudisGraphSession($client);
    $rows = $session->run('RETURN 1', []);

    expect($rows)->toHaveCount(1)
        ->and($rows[0]['n']['id'])->toBe('p1')
        ->and($rows[0]['n']['labels'])->toBe(['Person'])
        ->and($rows[0]['r']['type'])->toBe('OWNS')
        ->and($rows[0]['r']['start'])->toBe('1')
        ->and($rows[0]['list'])->toBe(['x'])
        ->and($rows[0]['map'])->toBe(['k' => 'v'])
        ->and($rows[0]['count'])->toBe(4);
});

it('keeps a null id when the node property is not a string', function (): void {
    $summary = null;
    $node = new Node(1, new CypherList(['Person']), new CypherMap(['id' => 99]), 'el-1');
    $result = new SummarizedResult($summary, [new CypherMap(['n' => $node])], ['n']);

    $client = Mockery::mock(ClientInterface::class);
    $client->shouldReceive('run')->once()->andReturn($result);

    $rows = new LaudisGraphSession($client)->run('RETURN 1');

    expect($rows[0]['n']['id'])->toBeNull();
});
