<?php

declare(strict_types=1);

use App\Enums\GraphDriver;
use App\Graph\GraphBindings;
use App\Graph\GraphClient;
use App\Graph\InMemoryGraphClient;
use App\Graph\Neo4jGraphClient;
use Illuminate\Container\Container;

it('binds the in-memory graph client', function (): void {
    $container = new Container();
    new GraphBindings($container)->register(GraphDriver::Memory);

    expect($container->make(GraphClient::class))->toBeInstanceOf(InMemoryGraphClient::class);
});

it('binds the neo4j graph client', function (): void {
    $container = new Container();
    $container->instance('config', app('config'));
    new GraphBindings($container)->register(GraphDriver::Neo4j);

    expect($container->bound(GraphClient::class))->toBeTrue()
        ->and($container->make(GraphClient::class))->toBeInstanceOf(Neo4jGraphClient::class);
});
