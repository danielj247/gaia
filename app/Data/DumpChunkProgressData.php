<?php

declare(strict_types=1);

namespace App\Data;

use App\Enums\DumpChunkPass;
use App\Enums\DumpChunkStatus;
use Spatie\LaravelData\Data;

final class DumpChunkProgressData extends Data
{
    public function __construct(
        public readonly string $id,
        public readonly int $chunk_index,
        public readonly DumpChunkPass $pass,
        public readonly DumpChunkStatus $status,
        public readonly int $line_start,
        public readonly int $line_end,
        public readonly int $entities_read,
        public readonly int $nodes_upserted,
        public readonly int $edges_upserted,
        public readonly ?string $error,
    ) {}
}
