<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Dump;

final readonly class IngestOfficialDump
{
    public function __construct(
        private EnsureGraphSchema $ensureSchema,
        private FetchOfficialList $fetch,
        private ParseFollowTheMoneyDump $parse,
    ) {}

    public function handle(string $list, ?int $limit): Dump
    {
        $this->ensureSchema->handle();

        $dump = $this->fetch->handle($list, $limit);

        return $this->parse->handle($dump);
    }
}
