<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\DumpStatus;
use App\Models\Dump;
use RuntimeException;

final readonly class FetchOfacSdn
{
    public function __construct(
        private DownloadOfficialSource $download,
        private ConvertOfacSdnXml $convert,
    ) {}

    public function handle(?int $limit): Dump
    {
        $dump = $this->download->handle(
            'ofac_sdn',
            $limit,
            config()->string('graph.official.ofac_sdn.url'),
            'xml',
        );

        $xmlPath = (string) $dump->path;
        $jsonlPath = storage_path('app/dumps/'.$dump->id.'.jsonl');

        try {
            $this->convert->handle($xmlPath, $jsonlPath);
        } catch (RuntimeException $exception) {
            $dump->update([
                'status' => DumpStatus::Failed,
                'error' => $exception->getMessage(),
            ]);

            throw $exception;
        } finally {
            if (is_file($xmlPath)) {
                unlink($xmlPath);
            }
        }

        $dump->update([
            'path' => $jsonlPath,
            'status' => DumpStatus::Pending,
        ]);

        return $dump->fresh() ?? $dump;
    }
}
