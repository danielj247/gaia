<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\IngestCompaniesHouseDump;
use App\Actions\ResumeDumpIngest;
use App\Enums\DumpStatus;
use App\Models\Dump;
use Illuminate\Console\Command;
use RuntimeException;
use Throwable;

final class IngestCompaniesHouseCommand extends Command
{
    protected $signature = 'graph:ingest-companies-house {product=ch_companies} {--limit=400} {--resume=} {--part=}';

    protected $description = 'Download a Companies House public register product and queue chunked graph merges. Omit --part on ch_psc to walk dated snapshot parts 1..N (one Dump each; delete after each; abort the next part if free disk is below 4 GB). A full unlimited PSC walk also requires about 24 GB free after companies are loaded.';

    public function handle(IngestCompaniesHouseDump $ingest, ResumeDumpIngest $resumeDump): int
    {
        $resume = $this->option('resume');

        try {
            $dump = is_string($resume) && $resume !== ''
                ? $resumeDump->handle($resume)
                : $this->start($ingest);
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        if ($dump->status === DumpStatus::Completed) {
            $this->info(sprintf(
                'Read %d entities, upserted %d node writes and %d edge writes.',
                $dump->entities_read,
                $dump->nodes_upserted,
                $dump->edges_upserted,
            ));

            return self::SUCCESS;
        }

        $this->info(sprintf(
            'Queued dump %s with %d chunks. Run php artisan queue:work to continue.',
            $dump->id,
            $dump->chunks()->count(),
        ));

        return self::SUCCESS;
    }

    private function start(IngestCompaniesHouseDump $ingest): Dump
    {
        $product = $this->argument('product');

        if ($product === '') {
            throw new RuntimeException('Product must be a string.');
        }

        $limitOption = $this->option('limit');
        $limit = is_numeric($limitOption) ? (int) $limitOption : 400;
        $limit = $limit > 0 ? $limit : null;

        $partOption = $this->option('part');
        $part = is_numeric($partOption) ? (int) $partOption : null;
        $part = $part !== null && $part > 0 ? $part : null;

        $this->info('Ingesting Companies House product '.$product.($limit === null ? '' : ' (limit '.$limit.')').($part === null ? '' : ' (part '.$part.')'));

        return $ingest->handle($product, $limit, $part);
    }
}
