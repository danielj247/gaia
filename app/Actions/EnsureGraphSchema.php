<?php

declare(strict_types=1);

namespace App\Actions;

use App\Graph\GraphClient;

final readonly class EnsureGraphSchema
{
    public function __construct(private GraphClient $graph) {}

    public function handle(): void
    {
        $this->graph->ensureSchema();
    }
}
