<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\DumpStatus;
use App\Models\Dump;
use RuntimeException;

final readonly class FetchCompaniesHouseCompanies
{
    public function __construct(
        private EnsureCompaniesHouseDiskBudget $disk,
        private ResolveCompaniesHouseSnapshot $resolve,
        private DownloadCompaniesHouseSource $download,
        private ConvertCompaniesHouseCsv $convert,
    ) {}

    public function handle(?int $limit): Dump
    {
        $this->disk->handle();

        $snapshot = $this->resolve->handle('ch_companies')[0];
        $dump = $this->download->handle(
            'ch_companies',
            $limit,
            $snapshot['url'],
            $snapshot['extension'],
            $snapshot['filename'],
        );

        $sourcePath = (string) $dump->path;
        $jsonlPath = storage_path('app/dumps/'.$dump->id.'.jsonl');

        try {
            $this->convert->handle($sourcePath, $jsonlPath, $limit);
        } catch (RuntimeException $exception) {
            $dump->update([
                'status' => DumpStatus::Failed,
                'error' => $exception->getMessage(),
            ]);

            throw $exception;
        } finally {
            if (is_file($sourcePath)) {
                unlink($sourcePath);
            }
        }

        $dump->update([
            'path' => $jsonlPath,
            'status' => DumpStatus::Pending,
        ]);

        return $dump->refresh();
    }
}
