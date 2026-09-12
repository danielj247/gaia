<?php

declare(strict_types=1);

namespace App\Data;

use Spatie\LaravelData\Data;

final class GraphSearchHitData extends Data
{
    public function __construct(
        public readonly string $id,
        public readonly string $label,
        public readonly string $caption,
    ) {}
}
