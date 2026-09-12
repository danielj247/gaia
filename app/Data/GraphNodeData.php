<?php

declare(strict_types=1);

namespace App\Data;

use Spatie\LaravelData\Data;

final class GraphNodeData extends Data
{
    /**
     * @param  array<string, mixed>  $properties
     */
    public function __construct(
        public readonly string $id,
        public readonly string $label,
        public readonly string $caption,
        public readonly array $properties,
    ) {}
}
