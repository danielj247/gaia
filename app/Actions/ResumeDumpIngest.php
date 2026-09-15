<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\DumpChunkStatus;
use App\Enums\DumpStatus;
use App\Models\Dump;
use App\Models\DumpChunk;
use RuntimeException;

final readonly class ResumeDumpIngest
{
    public function __construct(
        private ParseFollowTheMoneyDump $parse,
        private DispatchDumpChunks $dispatch,
    ) {}

    public function handle(string $dumpId): Dump
    {
        $dump = Dump::query()->find($dumpId);

        if ($dump === null) {
            throw new RuntimeException('Dump not found.');
        }

        if ($dump->status === DumpStatus::Completed) {
            return $dump;
        }

        if ($dump->chunks()->doesntExist()) {
            return $this->parse->handle($dump);
        }

        DumpChunk::query()
            ->where('dump_id', $dump->id)
            ->whereIn('status', [DumpChunkStatus::Failed, DumpChunkStatus::Processing])
            ->update([
                'status' => DumpChunkStatus::Pending,
                'error' => null,
            ]);

        $dump->update([
            'status' => DumpStatus::Ingesting,
            'error' => null,
        ]);

        $this->dispatch->handle($dump);

        return $dump->fresh() ?? $dump;
    }
}
