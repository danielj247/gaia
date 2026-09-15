<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\DumpStatus;
use App\Models\Dump;
use RuntimeException;

final readonly class FetchUkSanctionsList
{
    public function __construct(
        private DownloadOfficialSource $download,
        private ConvertUkSanctionsCsv $convert,
    ) {}

    public function handle(?int $limit): Dump
    {
        $dump = $this->download->handle(
            'uksl',
            $limit,
            config()->string('graph.official.uksl.url'),
            'csv',
        );

        $csvPath = (string) $dump->path;
        $jsonlPath = storage_path('app/dumps/'.$dump->id.'.jsonl');

        try {
            $this->convert->handle($csvPath, $jsonlPath);
        } catch (RuntimeException $exception) {
            $dump->update([
                'status' => DumpStatus::Failed,
                'error' => $exception->getMessage(),
            ]);

            throw $exception;
        } finally {
            if (is_file($csvPath)) {
                unlink($csvPath);
            }
        }

        $dump->update([
            'path' => $jsonlPath,
            'status' => DumpStatus::Pending,
        ]);

        return $dump->fresh() ?? $dump;
    }
}
