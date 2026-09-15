<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\DumpStatus;
use App\Models\Dump;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

final readonly class DownloadCompaniesHouseSource
{
    public function handle(string $dataset, ?int $limit, string $url, string $extension, string $filename): Dump
    {
        $dump = Dump::query()->create([
            'source' => 'companies_house',
            'dataset' => $dataset,
            'status' => DumpStatus::Downloading,
            'record_limit' => $limit,
            'attribution' => mb_trim(config()->string('graph.companies_house.attribution')).' Snapshot: '.$filename.'.',
        ]);

        $directory = storage_path('app/dumps');

        if (! is_dir($directory)) {
            try {
                mkdir($directory, 0755, true);
            } catch (Throwable) {
                //
            }

            if (! is_dir($directory)) {
                $dump->update([
                    'status' => DumpStatus::Failed,
                    'error' => 'Unable to create dump storage.',
                ]);

                throw new RuntimeException('Unable to create dump storage.');
            }
        }

        $path = $directory.'/'.$dump->id.'.'.$extension;

        $response = Http::timeout(config()->integer('graph.ingest.download_timeout'))
            ->withUserAgent(config()->string('graph.companies_house.user_agent'))
            ->sink($path)
            ->get($url);

        if ($response->failed() || ! is_file($path)) {
            $dump->update([
                'status' => DumpStatus::Failed,
                'error' => 'Download failed with HTTP '.$response->status().'.',
                'path' => $path,
            ]);

            throw new RuntimeException('Unable to download Companies House product '.$dataset.'.');
        }

        $dump->update(['path' => $path]);

        return $dump->refresh();
    }
}
