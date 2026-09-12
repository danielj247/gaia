<?php

declare(strict_types=1);

namespace App\Graph;

use App\Enums\GraphDriver;
use Illuminate\Contracts\Container\Container;
use Laudis\Neo4j\Authentication\Authenticate;
use Laudis\Neo4j\ClientBuilder;
use Laudis\Neo4j\Contracts\ClientInterface;

final readonly class GraphBindings
{
    public function __construct(private Container $app) {}

    public function register(GraphDriver $driver): void
    {
        if ($driver === GraphDriver::Neo4j) {
            $this->app->singleton(ClientInterface::class, function (): ClientInterface {
                return ClientBuilder::create()
                    ->withDriver(
                        'bolt',
                        config()->string('graph.uri'),
                        Authenticate::basic(
                            config()->string('graph.user'),
                            config()->string('graph.password'),
                        ),
                    )
                    ->withDefaultDriver('bolt')
                    ->build();
            });
            $this->app->singleton(GraphSession::class, LaudisGraphSession::class);
            $this->app->singleton(GraphClient::class, Neo4jGraphClient::class);

            return;
        }

        $this->app->singleton(GraphClient::class, InMemoryGraphClient::class);
    }
}
