<?php

declare(strict_types=1);

namespace App\Actions;

use RuntimeException;

final readonly class EnsureCompaniesHouseDiskBudget
{
    public function handle(?int $requiredBytes = null): void
    {
        $required = $requiredBytes ?? config()->integer('graph.companies_house.min_free_bytes');

        foreach ($this->paths() as $path) {
            $free = $this->freeBytes($path);

            if ($free < $required) {
                throw new RuntimeException(
                    'Not enough free disk for Companies House ingest (need at least '
                    .$this->gigabytes($required)
                    .' free on '.$path
                    .'; have '.$this->gigabytes($free).').',
                );
            }
        }
    }

    /**
     * @return list<string>
     */
    private function paths(): array
    {
        $configured = config('graph.companies_house.store_paths');
        $paths = [storage_path('app/dumps')];

        if (! is_array($configured)) {
            return $paths;
        }

        foreach ($configured as $path) {
            if (is_string($path) && $path !== '' && ! in_array($path, $paths, true)) {
                $paths[] = $path;
            }
        }

        return $paths;
    }

    private function freeBytes(string $path): int
    {
        $probe = $path;

        for ($i = 0; $i < 8 && $probe !== '/' && ! is_dir($probe); $i++) {
            $probe = dirname($probe);
        }

        $target = is_dir($probe) ? $probe : storage_path();

        return (int) (disk_free_space($target) ?: 0);
    }

    private function gigabytes(int $bytes): string
    {
        return number_format($bytes / (1024 * 1024 * 1024), 2).' GB';
    }
}
