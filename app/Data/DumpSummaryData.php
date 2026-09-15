<?php

declare(strict_types=1);

namespace App\Data;

use App\Enums\DumpStatus;
use Spatie\LaravelData\Data;

final class DumpSummaryData extends Data
{
    public function __construct(
        public readonly string $id,
        public readonly string $source,
        public readonly string $dataset,
        public readonly DumpStatus $status,
        public readonly int $entities_read,
        public readonly int $nodes_upserted,
        public readonly int $edges_upserted,
        public readonly int $chunks_total,
        public readonly int $chunks_completed,
        public readonly int $chunks_failed,
        public readonly int $error_count,
        public readonly ?string $error,
        public readonly string $created_at,
        public readonly string $updated_at,
    ) {}
}
