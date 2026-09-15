<?php

declare(strict_types=1);

namespace App\Data;

use Spatie\LaravelData\Data;

final class GraphStatsData extends Data
{
    /**
     * @param  list<string>  $datasets
     */
    public function __construct(
        public readonly int $nodes,
        public readonly int $edges,
        public readonly string $attribution,
        public readonly ?string $dataset,
        public readonly array $datasets = [],
    ) {}
}
