<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Dump;

final readonly class IngestCompaniesHouseDump
{
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
        $last = $this->parse->handle($this->fetch->handle('ch_psc', $limit, 1));

        for ($part = 2; $part <= $total; $part++) {
            $last = $this->parse->handle($this->fetch->handle('ch_psc', $limit, $part));
        }

        return $last;
    }
}
