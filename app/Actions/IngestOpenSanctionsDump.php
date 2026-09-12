<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Dump;

final readonly class IngestOpenSanctionsDump
{
    public function __construct(
        private EnsureGraphSchema $ensureSchema,
        private FetchOpenSanctionsDump $fetch,
        private ParseFollowTheMoneyDump $parse,
    ) {}

    public function handle(string $dataset, ?int $limit): Dump
    {
        $this->ensureSchema->handle();

        $dump = $this->fetch->handle($dataset, $limit);

        return $this->parse->handle($dump);
    }
}
