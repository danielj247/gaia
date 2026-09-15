<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\DumpStatus;
use App\Models\Dump;
use RuntimeException;

final readonly class FetchEuFsf
{
    public function __construct(
        private DownloadOfficialSource $download,
        private ConvertEuFsfXml $convert,
    ) {}

    public function handle(?int $limit): Dump
    {
        $configured = config('graph.official.eu_fsf.token');
        $token = is_string($configured) ? mb_trim($configured) : '';

        if ($token === '') {
            throw new RuntimeException('EU FSF token is not configured.');
        }

        $url = config()->string('graph.official.eu_fsf.url');
        $url .= (str_contains($url, '?') ? '&' : '?').'token='.rawurlencode($token);

        $dump = $this->download->handle('eu_fsf', $limit, $url, 'xml');
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
