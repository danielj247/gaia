<?php

declare(strict_types=1);

use App\Graph\LaudisGraphSession;
use Illuminate\Support\Sleep;
use Laudis\Neo4j\Contracts\ClientInterface;
use Laudis\Neo4j\Databags\Neo4jError;
use Laudis\Neo4j\Databags\SummarizedResult;
use Laudis\Neo4j\Exception\Neo4jException;
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

it('retries a statement after a transient deadlock and returns the eventual rows', function (): void {
    $summary = null;
    $result = new SummarizedResult($summary, [new CypherMap(['count' => 1])], ['count']);
    $deadlock = new Neo4jException([
        Neo4jError::fromMessageAndCode('Neo.TransientError.Transaction.DeadlockDetected', "ForsetiClient can't acquire EXCLUSIVE RELATIONSHIP(1)"),
    ]);

    $client = Mockery::mock(ClientInterface::class);
    $client->shouldReceive('run')->once()->andThrow($deadlock);
    $client->shouldReceive('run')->once()->andThrow($deadlock);
    $client->shouldReceive('run')->once()->andReturn($result);

    $rows = new LaudisGraphSession($client)->run('MERGE (n:Organization {id: $id})', ['id' => 'o1']);

    Sleep::assertSleptTimes(2);

    expect($rows)->toBe([['count' => 1]]);
});

it('gives up on a transient error after five attempts', function (): void {
    $deadlock = new Neo4jException([
        Neo4jError::fromMessageAndCode('Neo.TransientError.Transaction.DeadlockDetected', 'deadlock'),
    ]);

    $client = Mockery::mock(ClientInterface::class);
    $client->shouldReceive('run')->times(5)->andThrow($deadlock);

    expect(fn (): array => new LaudisGraphSession($client)->run('MERGE (n:Organization {id: $id})', ['id' => 'o1']))
        ->toThrow(Neo4jException::class, 'DeadlockDetected');

    Sleep::assertSleptTimes(4);
});

it('does not retry non-transient neo4j errors', function (): void {
    $client = Mockery::mock(ClientInterface::class);
    $client->shouldReceive('run')->once()->andThrow(new Neo4jException([
        Neo4jError::fromMessageAndCode('Neo.ClientError.Statement.SyntaxError', 'bad cypher'),
    ]));

    expect(fn (): array => new LaudisGraphSession($client)->run('MERGE (n:Organization {id: $id})', ['id' => 'o1']))
        ->toThrow(Neo4jException::class, 'SyntaxError');

    Sleep::assertNeverSlept();
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
