<?php

declare(strict_types=1);

namespace App\Graph;

final readonly class Neighborhood
{
    /**
     * @param  list<array{id: string, label: string, caption: string, properties: array<string, mixed>}>  $nodes
     * @param  list<array{id: string, type: string, source: string, target: string, properties: array<string, mixed>}>  $edges
     */
    public function __construct(
        public array $nodes,
        public array $edges,
        public bool $truncated,
    ) {}
}
