<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\IngestOfficialDump;
use App\Actions\ResumeDumpIngest;
use App\Enums\DumpStatus;
use App\Models\Dump;
use Illuminate\Console\Command;
use RuntimeException;
use Throwable;

final class IngestOfficialCommand extends Command
{
    protected $signature = 'graph:ingest-official {list=uksl} {--limit=400} {--resume=}';

    protected $description = 'Download an official government sanctions list and queue chunked graph merges';

    public function handle(IngestOfficialDump $ingest, ResumeDumpIngest $resumeDump): int
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

    private function start(IngestOfficialDump $ingest): Dump
    {
        $list = $this->argument('list');

        if (! is_string($list) || $list === '') {
            throw new RuntimeException('List must be a string.');
        }

        $limitOption = $this->option('limit');
        $limit = is_numeric($limitOption) ? (int) $limitOption : 400;
        $limit = $limit > 0 ? $limit : null;

        $this->info('Ingesting official list '.$list.($limit === null ? '' : ' (limit '.$limit.')'));

        return $ingest->handle($list, $limit);
    }
}
