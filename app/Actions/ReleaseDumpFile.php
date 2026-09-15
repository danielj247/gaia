<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Dump;

final readonly class ReleaseDumpFile
{
    public function handle(Dump $dump): Dump
    {
        $path = $dump->path;
        $root = storage_path('app/dumps');

        if (! is_string($path) || $path === '' || ! str_starts_with($path, $root)) {
            return $dump;
        }

        if (is_file($path)) {
            unlink($path);
        }

        $dump->update(['path' => null]);

        return $dump->fresh() ?? $dump;
    }
}
