<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Dump;

final readonly class ParseFollowTheMoneyDump
{
    public function __construct(
        private SliceDumpIntoChunks $slice,
        private DispatchDumpChunks $dispatch,
    ) {}

    public function handle(Dump $dump): Dump
    {
        $dump = $this->slice->handle($dump);

        if ($dump->chunks()->doesntExist()) {
            return $dump;
        }

        $this->dispatch->handle($dump);

        return $dump->fresh() ?? $dump;
    }
}
