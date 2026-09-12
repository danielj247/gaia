<?php

declare(strict_types=1);

namespace App\Data;

use Spatie\LaravelData\Data;

final class GraphEdgeData extends Data
{
    /**
     * @param  array<string, mixed>  $properties
     */
    public function __construct(
        public readonly string $id,
        public readonly string $type,
        public readonly string $source,
        public readonly string $target,
        public readonly array $properties,
    ) {}
}
