<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\DumpStatus;
use App\Models\Dump;
use Illuminate\Support\Sleep;
use RuntimeException;

final readonly class IngestCompaniesHouseDump
{
    private const int POLL_SECONDS = 10;

    public function __construct(
        private EnsureGraphSchema $ensureSchema,
        private EnsureCompaniesHouseDiskBudget $disk,
        private ResolveCompaniesHouseSnapshot $resolve,
        private FetchCompaniesHouseProduct $fetch,
        private ParseFollowTheMoneyDump $parse,
    ) {}

    public function handle(string $product, ?int $limit, ?int $part = null): Dump
    {
        $this->ensureSchema->handle();

        if (mb_trim($product) === 'ch_psc' && $part === null) {
            return $this->ingestAllPscParts($limit);
        }

        return $this->parse->handle($this->fetch->handle($product, $limit, $part));
    }

    private function ingestAllPscParts(?int $limit): Dump
    {
        if ($limit === null) {
            $this->disk->handle(config()->integer('graph.companies_house.psc_min_free_bytes'));
        }

        $total = $this->resolve->handle('ch_psc', 1)[0]['parts'];
        $last = $this->ingestPscPart($limit, 1);

        for ($part = 2; $part <= $total; $part++) {
            $last = $this->ingestPscPart($limit, $part);
        }

        return $last;
    }

    /**
     * Queue one part, then block until the workers have completed it and its jsonl is
     * released. The disk budget assumes one zip plus one jsonl at a time, so the next
     * download may not start while this part's file is still on disk.
     */
    private function ingestPscPart(?int $limit, int $part): Dump
    {
        $dump = $this->parse->handle($this->fetch->handle('ch_psc', $limit, $part));

        while (! $this->isSettled($dump)) {
            Sleep::for(self::POLL_SECONDS)->seconds();
            $dump->refresh();
        }

        if ($dump->status === DumpStatus::Failed) {
            throw new RuntimeException(sprintf(
                'PSC part %d failed: %s Resume dump %s before walking on.',
                $part,
                (string) $dump->error,
                $dump->id,
            ));
        }

        return $dump;
    }

    private function isSettled(Dump $dump): bool
    {
        return $dump->status === DumpStatus::Failed
            || ($dump->status === DumpStatus::Completed && $dump->path === null);
    }
}
