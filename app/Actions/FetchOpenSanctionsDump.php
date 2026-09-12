<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\DumpStatus;
use App\Models\Dump;
use Illuminate\Support\Facades\Http;
use RuntimeException;

final readonly class FetchOpenSanctionsDump
{
    public function handle(string $dataset, ?int $limit): Dump
    {
        $dataset = mb_trim($dataset);

        if ($dataset === '' || ! preg_match('/^[a-z0-9_]+$/', $dataset)) {
            throw new RuntimeException('Dataset must be a lowercase OpenSanctions dataset id.');
        }

        $attribution = config()->string('graph.opensanctions.attribution');
        $filename = config()->string('graph.opensanctions.filename');
        $baseUrl = mb_rtrim(config()->string('graph.opensanctions.base_url'), '/');
        $url = $baseUrl.'/'.$dataset.'/'.$filename;

        $dump = Dump::query()->create([
            'source' => 'opensanctions',
            'dataset' => $dataset,
            'status' => DumpStatus::Downloading,
            'record_limit' => $limit,
            'attribution' => $attribution,
        ]);

        $directory = storage_path('app/dumps');

        if (! is_dir($directory) && ! mkdir($directory, 0755, true) && ! is_dir($directory)) {
            $dump->update([
                'status' => DumpStatus::Failed,
                'error' => 'Unable to create dump storage.',
            ]);

            throw new RuntimeException('Unable to create dump storage.');
        }

        $path = $directory.'/'.$dump->id.'.ftm.jsonl';

        $response = Http::timeout(120)
            ->sink($path)
            ->get($url);

        if ($response->failed() || ! is_file($path)) {
            $dump->update([
                'status' => DumpStatus::Failed,
                'error' => 'Download failed with HTTP '.$response->status().'.',
                'path' => $path,
            ]);

            throw new RuntimeException('Unable to download OpenSanctions dataset.');
        }

        $this->normalizeToJsonLines($path);

        $dump->update([
            'path' => $path,
            'status' => DumpStatus::Pending,
        ]);

        return $dump->fresh() ?? $dump;
    }

    private function normalizeToJsonLines(string $path): void
    {
        $contents = file_get_contents($path);

        if (! is_string($contents) || $contents === '') {
            return;
        }

        $trimmed = mb_ltrim($contents);

        if (! str_starts_with($trimmed, '[')) {
            return;
        }

        $decoded = json_decode($contents, true);

        if (! is_array($decoded)) {
            return;
        }

        $lines = [];

        foreach ($decoded as $row) {
            if (! is_array($row)) {
                continue;
            }

            $lines[] = json_encode($row, JSON_THROW_ON_ERROR);
        }

        file_put_contents($path, implode("\n", $lines)."\n");
    }
}
