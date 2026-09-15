<?php

declare(strict_types=1);

namespace App\Actions;

use Illuminate\Support\Facades\Http;
use RuntimeException;

final readonly class ResolveCompaniesHouseSnapshot
{
    /**
     * @return list<array{url: string, filename: string, extension: string, part: int|null, parts: int}>
     */
    public function handle(string $product, ?int $part = null): array
    {
        $product = mb_trim($product);
        $names = $this->productNames();

        if ($product === '' || ! in_array($product, $names, true)) {
            throw new RuntimeException(
                'Product must be a published Companies House product: '.($names === [] ? 'ch_companies, ch_psc' : implode(', ', $names)).'.',
            );
        }

        $snapshots = $this->snapshots($product);

        if ($snapshots === []) {
            throw new RuntimeException('Unable to resolve the latest Companies House '.$product.' snapshot.');
        }

        if ($product === 'ch_companies' || $part === null) {
            return $product === 'ch_companies' ? array_slice($snapshots, 0, 1) : $snapshots;
        }

        foreach ($snapshots as $snapshot) {
            if ($snapshot['part'] === $part) {
                return [$snapshot];
            }
        }

        throw new RuntimeException('Companies House PSC part '.$part.' is not on the current snapshot.');
    }

    /**
     * @return list<array{url: string, filename: string, extension: string, part: int|null, parts: int}>
     */
    private function snapshots(string $product): array
    {
        $override = config('graph.companies_house.'.$product.'.url');

        if (is_string($override) && mb_trim($override) !== '') {
            $this->assertNotSinglePscZip($override);

            return [$this->describe($override, $product === 'ch_psc' ? 1 : null, $product === 'ch_psc' ? 1 : 1)];
        }

        $html = $this->fetchIndex(config()->string('graph.companies_house.'.$product.'.index_url'));

        return $product === 'ch_psc'
            ? $this->pscParts($html)
            : $this->companyZips($html);
    }

    /**
     * @return list<array{url: string, filename: string, extension: string, part: int|null, parts: int}>
     */
    private function companyZips(string $html): array
    {
        if (preg_match_all('/href="((?:https?:\/\/[^"]+\/)?BasicCompanyDataAsOneFile-\d{4}-\d{2}-\d{2}\.zip)"/i', $html, $matches) === 0) {
            return [];
        }

        $snapshots = [];

        foreach ($matches[1] as $filename) {
            $snapshots[] = $this->describe($this->absoluteUrl((string) $filename), null, 1);
        }

        return $snapshots;
    }

    /**
     * @return list<array{url: string, filename: string, extension: string, part: int|null, parts: int}>
     */
    private function pscParts(string $html): array
    {
        if (preg_match_all('/href="((?:https?:\/\/[^"]+\/)?psc-snapshot-\d{4}-\d{2}-\d{2}_(\d+)of(\d+)\.zip)"/i', $html, $matches, PREG_SET_ORDER) === 0) {
            return [];
        }

        $snapshots = [];

        foreach ($matches as $match) {
            $filename = (string) $match[1];
            $this->assertNotSinglePscZip($filename);
            $snapshots[] = $this->describe(
                $this->absoluteUrl($filename),
                (int) $match[2],
                (int) $match[3],
            );
        }

        usort($snapshots, fn (array $left, array $right): int => ($left['part'] ?? 0) <=> ($right['part'] ?? 0));

        return $snapshots;
    }

    /**
     * @return array{url: string, filename: string, extension: string, part: int|null, parts: int}
     */
    private function describe(string $url, ?int $part, int $parts): array
    {
        $filename = basename((string) parse_url($url, PHP_URL_PATH));

        if ($filename === '' || $filename === '/' || $filename === '.') {
            $filename = basename($url);
        }

        $extension = str_ends_with(mb_strtolower($filename), '.csv') ? 'csv' : 'zip';

        return [
            'url' => $url,
            'filename' => $filename,
            'extension' => $extension,
            'part' => $part,
            'parts' => $parts,
        ];
    }

    private function fetchIndex(string $url): string
    {
        $response = Http::timeout(config()->integer('graph.ingest.download_timeout'))
            ->withUserAgent(config()->string('graph.companies_house.user_agent'))
            ->get($url);

        if ($response->failed()) {
            throw new RuntimeException('Unable to read the Companies House download index (HTTP '.$response->status().').');
        }

        return $response->body();
    }

    private function absoluteUrl(string $filename): string
    {
        if (str_starts_with($filename, 'http://') || str_starts_with($filename, 'https://')) {
            return $filename;
        }

        return mb_rtrim(config()->string('graph.companies_house.download_base'), '/').'/'.$filename;
    }

    private function assertNotSinglePscZip(string $url): void
    {
        if (preg_match('/persons-with-significant-control-snapshot-/i', $url) === 1) {
            throw new RuntimeException('Refusing the single-file Companies House PSC zip; download dated multi-file parts instead.');
        }
    }

    /**
     * @return list<string>
     */
    private function productNames(): array
    {
        $allowed = config('graph.companies_house.products');

        if (! is_array($allowed)) {
            return [];
        }

        $names = [];

        foreach ($allowed as $name) {
            if (is_string($name) && $name !== '') {
                $names[] = $name;
            }
        }

        return $names;
    }
}
