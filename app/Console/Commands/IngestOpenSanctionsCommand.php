<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\IngestOpenSanctionsDump;
use Illuminate\Console\Command;
use Throwable;

final class IngestOpenSanctionsCommand extends Command
{
    protected $signature = 'graph:ingest-opensanctions {dataset=us_ofac_sdn} {--limit=400}';

    protected $description = 'Download a public OpenSanctions FollowTheMoney dump and merge it into the graph';

    public function handle(IngestOpenSanctionsDump $ingest): int
    {
        $dataset = $this->argument('dataset');

        if ($dataset === '') {
            $this->error('Dataset must be a string.');

            return self::FAILURE;
        }
        $limitOption = $this->option('limit');
        $limit = is_numeric($limitOption) ? (int) $limitOption : 400;
        $limit = $limit > 0 ? $limit : null;

        $this->info('Ingesting OpenSanctions dataset '.$dataset.($limit === null ? '' : ' (limit '.$limit.')'));

        try {
            $dump = $ingest->handle($dataset, $limit);
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info(sprintf(
            'Read %d entities, upserted %d node writes and %d edge writes.',
            $dump->entities_read,
            $dump->nodes_upserted,
            $dump->edges_upserted,
        ));

        return self::SUCCESS;
    }
}
