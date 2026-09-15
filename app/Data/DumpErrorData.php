<?php

declare(strict_types=1);

namespace App\Data;

use Spatie\LaravelData\Data;

final class DumpErrorData extends Data
{
    public function __construct(
        public readonly string $id,
        public readonly ?string $dump_chunk_id,
        public readonly ?int $line_number,
        public readonly string $code,
        public readonly string $message,
        public readonly string $created_at,
    ) {}
}
